<?php

namespace LogModule;

use Nette\Utils\Finder;


class ExceptionPresenter extends BaseLogPresenter
{

	public function renderDefault()
	{
		$this->template->errors = $this->getErrorList();
	}



	public function renderShow($file)
	{
		$dump = file_get_contents($this->getLogDir() . "/$file");
		$this->sendResponse(new \Nette\Application\Responses\TextResponse($dump));
	}



	protected function getErrorList()
	{
		$errors = [];
		foreach ($this->getDumps() as $dump) {
			$errors[] = $this->parseDump($dump);
		}
		return array_reverse($errors);
	}



	protected function parseDump($file)
	{
		$res = file_get_contents($file->getRealPath());
		$match = [];
		preg_match('~<div id="netteBluescreenError" class="panel">\s*<h1>(?P<title>.*?)</h1>\s*<p>(?P<text>.*?)\s*<a~ims', $res, $match);

		return (object) [
			'title' => isset($match['title']) ? $match['title'] : '',
			'text' => isset($match['text']) ? $match['text'] : '',
			'file' => $file->getFileName(),
			'ctime' => $file->getCTime(),
		];
	}



	protected function getDumps()
	{
		return Finder::findFiles('exception-*.html')->from($this->getLogDir());
	}



	protected function getLogDir()
	{
		return $this->context->parameters['appDir'] . '/../log';
	}

}
