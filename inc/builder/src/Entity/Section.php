<?php

namespace Schilo\Builder\Entity;

class Section
{
    private $type = 'paragraphe';
    private $title = '';
    private $content = '';
    private $customClass = '';
    private $order = 0;
    private $data = array();

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = sanitize_key((string) $type);
        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = sanitize_text_field((string) $title);
        return $this;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = (string) $content;
        return $this;
    }

    public function getCustomClass()
    {
        return $this->customClass;
    }

    public function setCustomClass($customClass)
    {
        $this->customClass = sanitize_text_field((string) $customClass);
        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = (int) $order;
        return $this;
    }

    public function getData()
    {
        return is_array($this->data) ? $this->data : array();
    }

    public function setData($data)
    {
        $this->data = is_array($data) ? $data : array();
        return $this;
    }

    public function toArray()
    {
        return array(
            'type' => $this->getType(),
            'title' => $this->getTitle(),
            'content' => $this->getContent(),
            'custom_class' => $this->getCustomClass(),
            'order' => $this->getOrder(),
            'data' => $this->getData(),
        );
    }

    public static function fromArray($data)
    {
        $data = is_array($data) ? $data : array();

        $section = new self();
        $section->setType(isset($data['type']) ? $data['type'] : 'paragraphe')
            ->setTitle(isset($data['title']) ? $data['title'] : '')
            ->setContent(isset($data['content']) ? $data['content'] : '')
            ->setCustomClass(isset($data['custom_class']) ? $data['custom_class'] : '')
            ->setOrder(isset($data['order']) ? (int) $data['order'] : 0)
            ->setData(isset($data['data']) && is_array($data['data']) ? $data['data'] : array());

        return $section;
    }
}
