<?php

namespace FrontModule;


class TagPresenter extends BaseFrontPresenter
{

	/** @persistent */
	public $tid;

	/** @var Tag */
	protected $tag;



	public function startup()
	{
		parent::startup();
		$this->tag = $this->context->tags->findOneBy(['id' => $this->tid]);
	}


	public function renderDefault()
	{
		$this->template->tags = $this->context->tags->findBy(['display' => TRUE]);
		$this->template->selected = $this->tag;
	}

}