<?php
/**
 * Port de USX_Verset_Processor (plugin Usx-import,
 * class-usx-verset-processor.php) — parsing DOM des <para>/<verse>/<note>
 * USX vers wp_usx_chapters/wp_usx_verses/wp_usx_notes. Style fonctionnel
 * inchangé : un tableau $context passé/retourné par valeur, alimenté par
 * Schilo_Usx_Importer::parse_usx_normalized().
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Verset_Processor {

	/**
	 * Traitement principal d'un <para> : 1) versets, 2) notes.
	 */
	public function process_verses( $node, $context ) {
		$context = $this->init_open_verse_state( $context );
		$context = $this->process_verses_dom( $node, $context );
		$context = $this->process_para_notes( $node, $context );
		return $context;
	}

	/** À appeler à la fin d'un livre/fichier si un verset reste ouvert. */
	public function flush_open_verse( $context ) {
		$context = $this->init_open_verse_state( $context );

		if ( $context['open_verse_number'] !== null && trim( $context['open_verse_text'] ) !== '' ) {
			$context = $this->save_verse_to_db( $context['open_verse_number'], $context['open_verse_text'], $context );
		}

		return $this->reset_open_verse_state( $context );
	}

	// ---------- Gestion des VERSETS ----------
	private function process_verses_dom( $node, $context ) {
		$para_xml = $node->asXML();
		if ( ! $para_xml ) {
			return $context;
		}

		$dom = new DOMDocument();
		$dom->loadXML( '<?xml version="1.0" encoding="UTF-8"?>' . $para_xml, LIBXML_NOERROR | LIBXML_NOWARNING );

		$paras = $dom->getElementsByTagName( 'para' );
		if ( $paras->length === 0 ) {
			return $context;
		}

		$para = $paras->item( 0 );

		$verse_para = $para->cloneNode( true );
		$this->remove_notes_from_dom( $verse_para );

		$para_vid = $verse_para->hasAttribute( 'vid' ) ? trim( (string) $verse_para->getAttribute( 'vid' ) ) : '';

		foreach ( $verse_para->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'verse' ) {
				/** @var DOMElement $child */

				if ( $child->hasAttribute( 'number' ) ) {
					if ( $context['open_verse_number'] !== null && trim( $context['open_verse_text'] ) !== '' ) {
						$context = $this->save_verse_to_db( $context['open_verse_number'], $context['open_verse_text'], $context );
						$context = $this->reset_open_verse_state( $context );
					}

					$context['open_verse_number'] = (int) $child->getAttribute( 'number' );
					$context['open_verse_sid']    = $child->hasAttribute( 'sid' ) ? trim( (string) $child->getAttribute( 'sid' ) ) : '';
					$context['open_verse_text']   = '';
					continue;
				}

				if ( $child->hasAttribute( 'eid' ) ) {
					if ( $context['open_verse_number'] !== null ) {
						if ( trim( $context['open_verse_text'] ) !== '' ) {
							$context = $this->save_verse_to_db( $context['open_verse_number'], $context['open_verse_text'], $context );
						}
						$context = $this->reset_open_verse_state( $context );
					}
					continue;
				}
			}

			if ( $context['open_verse_number'] !== null ) {
				$context['open_verse_text'] .= $this->extract_inline_text( $child );
			}
		}

		return $context;
	}

	// ---------- Gestion des NOTES ----------
	public function process_para_notes( $node, $context ) {
		$para_xml = $node->asXML();
		if ( ! $para_xml ) {
			return $context;
		}

		$dom = new DOMDocument();
		$dom->loadXML( '<?xml version="1.0" encoding="UTF-8"?>' . $para_xml, LIBXML_NOERROR | LIBXML_NOWARNING );

		$paras = $dom->getElementsByTagName( 'para' );
		if ( $paras->length === 0 ) {
			return $context;
		}

		$para = $paras->item( 0 );

		$verse_number_for_para = $this->resolve_para_verse_number( $para, $context );
		if ( $verse_number_for_para === null ) {
			return $context;
		}

		foreach ( $para->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'note' ) {
				$context = $this->save_note_to_db( $child, $verse_number_for_para, $context );
			}
		}

		return $context;
	}

	/**
	 * Détermine à quel verset rattacher les notes d'un para. Priorité :
	 * 1) <verse number="x">, 2) para vid="BOOK CH:VR", 3) verset ouvert courant.
	 */
	private function resolve_para_verse_number( DOMElement $para, array $context ): ?int {
		foreach ( $para->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'verse' && $child->hasAttribute( 'number' ) ) {
				return (int) $child->getAttribute( 'number' );
			}
		}

		if ( $para->hasAttribute( 'vid' ) ) {
			$vid          = trim( (string) $para->getAttribute( 'vid' ) );
			$verse_number = $this->extract_verse_number_from_ref( $vid );
			if ( $verse_number !== null ) {
				return $verse_number;
			}
		}

		if ( isset( $context['open_verse_number'] ) && $context['open_verse_number'] !== null ) {
			return (int) $context['open_verse_number'];
		}

		return null;
	}

	/** Extrait le numéro de verset depuis une référence USX du type "PSA 24:1". */
	private function extract_verse_number_from_ref( string $ref ): ?int {
		if ( preg_match( '/:(\d+)(?:-\d+)?$/', $ref, $m ) ) {
			return (int) $m[1];
		}
		return null;
	}

	/**
	 * Extrait le texte utile d'un nœud inline. Texte brut conservé, <note>
	 * ignorées pour le verset, autres balises inline conservées via textContent.
	 */
	private function extract_inline_text( DOMNode $node ): string {
		if ( $node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE ) {
			return $node->nodeValue;
		}

		if ( $node->nodeType === XML_ELEMENT_NODE ) {
			if ( $node->nodeName === 'note' ) {
				return '';
			}
			return $node->textContent;
		}

		return '';
	}

	/** Sauvegarde d'une NOTE (liée à son verset) */
	private function save_note_to_db( $note_elem, $verse_number, $context ) {
		if ( $verse_number === null ) {
			return $context;
		}

		$wpdb       = $context['wpdb'];
		$prefix     = $context['prefix'];
		$chapter_id = $this->get_or_create_chapter( $context );

		$verse_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$prefix}usx_verses WHERE chapter_id = %d AND verse = %d", $chapter_id, $verse_number )
		);

		if ( ! $verse_row ) {
			return $context;
		}

		$note_type = $note_elem->getAttribute( 'style' ) ?: null;
		$caller    = $note_elem->getAttribute( 'caller' ) ?: null;
		[ $content, $references ] = $this->parse_note_content( $note_elem );

		$wpdb->insert(
			"{$prefix}usx_notes",
			[
				'verse_id'        => $verse_row->id,
				'note_type'       => $note_type,
				'caller'          => $caller,
				'content'         => trim( $content ),
				'references_json' => json_encode( $references, JSON_UNESCAPED_UNICODE ),
			]
		);

		$context['nb_notes']++;

		return $context;
	}

	/** Extraction du contenu textuel et des références d'une note. */
	private function parse_note_content( $note_elem ) {
		$content    = '';
		$references = [];

		foreach ( $note_elem->childNodes as $child ) {
			if ( $child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE ) {
				$content .= $child->nodeValue;
			}

			if ( $child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'char' ) {
				$content .= $child->textContent;

				if ( $child->getAttribute( 'style' ) === 'xt' ) {
					foreach ( $child->getElementsByTagName( 'ref' ) as $ref ) {
						$references[] = [
							'loc'  => $ref->getAttribute( 'loc' ),
							'text' => $ref->nodeValue,
						];
					}
				}
			}
		}

		return [ $content, $references ];
	}

	// ---------- Sauvegarde d'un verset en base ----------
	private function save_verse_to_db( $verse_number, $verse_text, $context ) {
		$verse_text = trim( $verse_text );
		if ( $verse_text === '' ) {
			return $context;
		}

		$wpdb       = $context['wpdb'];
		$prefix     = $context['prefix'];
		$chapter_id = $this->get_or_create_chapter( $context );

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$prefix}usx_verses WHERE chapter_id = %d AND verse = %d", $chapter_id, $verse_number )
		);

		if ( $existing ) {
			$wpdb->update( "{$prefix}usx_verses", [ 'verse_text' => $verse_text ], [ 'id' => $existing ] );

			$context['log'][] = "Verset mis à jour {$context['book_code']} {$context['chapter']}:$verse_number : " .
				mb_substr( $verse_text, 0, 60 ) . ( mb_strlen( $verse_text ) > 60 ? '...' : '' );

			return $context;
		}

		$context['section_order']++;
		$wpdb->insert(
			"{$prefix}usx_verses",
			[
				'chapter_id'    => $chapter_id,
				'verse'         => $verse_number,
				'verse_text'    => $verse_text,
				'section_order' => $context['section_order'],
			]
		);

		$context['count']++;
		$context['log'][] = "Verset {$context['book_code']} {$context['chapter']}:$verse_number : " .
			mb_substr( $verse_text, 0, 60 ) . ( mb_strlen( $verse_text ) > 60 ? '...' : '' );

		return $context;
	}

	// ---------- Gestion chapitre ----------
	private function get_or_create_chapter( &$context ) {
		$wpdb           = $context['wpdb'];
		$prefix         = $context['prefix'];
		$book_id        = $context['book_id'];
		$chapter_number = $context['chapter'];
		$title          = $context['chapter_title'];
		$sub_title      = $context['sub_title'];

		$chapter_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$prefix}usx_chapters WHERE book_id = %d AND number = %d", $book_id, $chapter_number )
		);

		if ( ! $chapter_row ) {
			$wpdb->insert(
				"{$prefix}usx_chapters",
				[
					'book_id'   => $book_id,
					'number'    => $chapter_number,
					'title'     => $title,
					'sub_title' => $sub_title,
				]
			);
			$chapter_id        = $wpdb->insert_id;
			$context['log'][] = "Chapitre ajouté : {$context['book_code']} $chapter_number";
		} else {
			$chapter_id = $chapter_row->id;
			$update     = [];

			if ( ! empty( $title ) ) {
				$update['title'] = $title;
			}
			if ( ! empty( $sub_title ) ) {
				$update['sub_title'] = $sub_title;
			}

			if ( $update ) {
				$wpdb->update( "{$prefix}usx_chapters", $update, [ 'id' => $chapter_id ] );
			}
		}

		$context['chapter_id'] = $chapter_id;
		return $chapter_id;
	}

	// ---------- Titres et sous-titres de chapitres ----------
	public function handle_new_chapter_title( $title, $context ) {
		$context['chapter_title'] = $title;
		$this->get_or_create_chapter( $context );
		$context['nb_titles']++;
		return $context;
	}

	public function handle_new_sub_title( $subtitle, $context ) {
		$context['sub_title'] = $subtitle;
		$this->get_or_create_chapter( $context );
		$context['nb_subtitles']++;
		return $context;
	}

	/** Supprime toutes les balises <note> du DOM (et leur contenu). */
	private function remove_notes_from_dom( $parent ) {
		foreach ( iterator_to_array( $parent->childNodes ) as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE && $child->nodeName === 'note' ) {
				$parent->removeChild( $child );
			} elseif ( $child->hasChildNodes() ) {
				$this->remove_notes_from_dom( $child );
			}
		}
	}

	private function init_open_verse_state( array $context ): array {
		if ( ! array_key_exists( 'open_verse_number', $context ) ) {
			$context['open_verse_number'] = null;
		}
		if ( ! array_key_exists( 'open_verse_sid', $context ) ) {
			$context['open_verse_sid'] = '';
		}
		if ( ! array_key_exists( 'open_verse_text', $context ) ) {
			$context['open_verse_text'] = '';
		}

		return $context;
	}

	private function reset_open_verse_state( array $context ): array {
		$context['open_verse_number'] = null;
		$context['open_verse_sid']    = '';
		$context['open_verse_text']   = '';
		return $context;
	}
}
