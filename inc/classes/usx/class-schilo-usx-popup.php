<?php
/**
 * Port de USX_Popup_UI::inject_popup_script() (plugin Usx-import,
 * Service/class-usx-popup-ui.php) — même structure HTML et même
 * comportement JS (clic/survol pour ouvrir, clic sur fermeture/overlay
 * pour fermer). Contrairement à l'original, le CSS n'est PAS injecté en
 * inline ici : assets/css/usx-integration.css (chargé par Schilo_Assets)
 * gère l'apparence via les classes .bible-overlay/.bible-popup/
 * .popup-header/.popup-content/.popup-footer, avec les tokens du thème.
 */
defined( 'ABSPATH' ) || exit;

final class Schilo_Usx_Popup {

	/**
	 * Injecte le HTML + JS de la popup Bible (appelée via
	 * add_action('wp_footer', ['Schilo_Usx_Popup', 'inject_popup_script'])
	 * par Schilo_Usx_Shortcodes::render_bib_shortcode()).
	 */
	public static function inject_popup_script() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<div class="bible-overlay"></div>
		<div class="bible-popup">
			<div class="popup-header">
				<span class="popup-ref"></span>
				<span class="close-btn">✖</span>
			</div>
			<div class="popup-content"></div>
			<div class="popup-footer">
				<span class="copyright"></span>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', () => {
			const overlay = document.querySelector('.bible-overlay');
			const popup = document.querySelector('.bible-popup');
			const refEl = popup.querySelector('.popup-ref');
			const contentEl = popup.querySelector('.popup-content');
			const copyEl = popup.querySelector('.copyright');
			const closeBtn = popup.querySelector('.close-btn');
			let hoverTimeout; let isOpen = false;

			function showPopup(el) {
				refEl.textContent = el.dataset.ref;
				contentEl.innerHTML = el.dataset.content;
				copyEl.textContent = el.dataset.copyright || 'Copyright';
				overlay.style.display = 'block';
				popup.style.display = 'block';
				isOpen = true;
			}

			function hidePopup() {
				popup.style.display = 'none';
				overlay.style.display = 'none';
				isOpen = false;
			}

			document.querySelectorAll('.bible-ref').forEach(el => {
				el.addEventListener('click', e => { e.preventDefault(); isOpen ? hidePopup() : showPopup(el); });
				el.addEventListener('mouseenter', () => { if (isOpen) return; clearTimeout(hoverTimeout); hoverTimeout = setTimeout(() => { if (!isOpen) showPopup(el); }, 400); });
				el.addEventListener('mouseleave', () => clearTimeout(hoverTimeout));
			});

			closeBtn.addEventListener('click', hidePopup);
			overlay.addEventListener('click', hidePopup);
		});
		</script>
		<?php
	}
}
