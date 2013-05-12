<?php

namespace FrontModule;

use Nette\Application\UI\Form;
use Nette\Caching\Cache;
use Model\NetteUser as ROLE;


class WatchPresenter extends BaseFrontPresenter
{

	/**
	 * @persistent
	 * @var int
	 */
	public $vid;

	/** @var \Video */
	protected $video;

	/**
	 * @persistent
	 * @var int
	 */
	public $id;

	/** @var \Category */
	protected $category;



	public function startup()
	{
		parent::startup();

		$this->video = $this->context->videos->find($this->vid);

		if (in_array($this->action, ['add', 'redirect'])) {
			return;
		}

		if ($this->video && !$this->id) {
			$this->redirect(301, 'this', [
				'id' => $this->video->getOneCategoryId(),
			]);
		}

		$this->category = $this->context->categories->find($this->id);
		if (!$this->category || !$this->video || !$this->category->containsVideo($this->video)) {
			throw new \Nette\Application\BadRequestException;
		}
	}



	public function actionRedirect($youtube_id)
	{
		$video = $this->context->videos->findOneBy(['youtube_id' => $youtube_id]);
		if (!$video) {
			throw new \Nette\Application\BadRequestException;
		}
		$this->redirect('default', [
			'id' => $video->getOneCategoryId(),
			'vid' => $video->id,
			'youtube_id' => NULL,
		]);
	}



	public function renderAlias()
	{
		$this->redirect('default');
	}



	public function renderDefault($autoplay = 0)
	{
		$this->template->autoplay = $autoplay;
		$this->template->video = $this->video;
		$this->template->category = $this->category;

		$this->template->subtitles = $this->context->amara->getSubtitles($this->video);
	}



	public function renderAdd()
	{
		$this['videoForm']['categories']->setDefaultValue([$this->id]);
	}



	public function handleUpdateProgress($seconds)
	{
		if ($this->ajax && $this->user->loggedIn) {
			$this->user->entity->setProgress($this->video, $this->category, $seconds, function() {
				// onVideoWatched
				$task = $this->user->entity->getTaskForVideo($this->video);
				if ($task && !$task->completed) {
					$cache = new Cache($this->context->cacheStorage);
					$cache->clean([Cache::TAGS => $this->task->getTagsToInvalidate()]);
					$task->setCompleted()->update();
				}
			});

			$cache = new Cache($this->context->cacheStorage, 'dynamic_css');
			$cache->clean([Cache::TAGS => "watched/{$this->user->id}"]);

			$this->sendJson(['status' => 'success']);
		}

		$this->terminate();
	}



	public function createComponentAliasForm($name)
	{
		$form = $this->createForm($name);

		$form->addText('alias', 'Alias');

		$form->addSubmit('send', 'Přidat')->controlPrototype->class = "simple-button blue";
		return $form;
	}



	public function onSuccessAliasForm($form)
	{
		$v = $form->values;
		if ($this->video->addAlias($v->alias)) {
			$this->flashMessage('Alias byl úspěšně přidán.');
			$this->redirect('default');
		} else {
			$form->addError('Tento alias je už používaný.');
		}
	}



	public function createComponentVideoForm($name)
	{
		$form = $this->createForm($name);

		$form->addText('youtube_id', 'Youtube ID', NULL, 11); // every youtube_id has 11 chars
		$form->addText('label', 'Název');
		$form->addTextArea('description', 'Popis');
		$form->addMultiSelect('tags', 'Tagy', $this->context->tags->getFill());
		$form->addMultiSelect('categories', 'Kategorie', $this->context->categories->getFill());
		$form->addSelect('author_id', 'Dabing', $this->context->authors->getFill());
		$form->addSelect('exercise_id', 'Cvičení', $this->context->exercises->getFill());
		$form->addText('external_exercise_url', 'Externí cvičení (url)');

		$form->addSubmit('send', 'Uložit')->controlPrototype->class = "simple-button green";
		return $form;
	}



	public function onSuccessVideoForm($form)
	{
		$v = $form->values;

		if (($this->action === 'add' || $this->video->youtube_id !== $v->youtube_id)
		 && $this->context->videos->findBy(['youtube_id' => $v->youtube_id])->count()) {
			$form->addError('Tohle video (id) už v databázi máme.');
			return FALSE;
		}

		$author_id = $v->author_id != 0 ? $v->author_id : NULL;

		if ($this->action === 'add') {
			if (!$this->user->isInrole(ROLE::ADDER)) {
				throw new \Nette\Application\ForbiddenRequestException;
			}

			try {
				$vid = $this->context->videos->insert([
					'label' => $v->label,
					'description' => $v->description,
					'youtube_id' => $v->youtube_id,
					'author_id' => $author_id,
					'exercise_id' => $v->exercise_id == 0 ? NULL : $v->exercise_id,
					'external_exercise_url' => $v->external_exercise_url,
				]);
			} catch (\PDOException $e) {
				if ($e->getCode() != 23000) {
					throw $e;
				}
				$form->addError('Toto jméno má už jiné video zabrané.');
				return FALSE;
			}

			$video = $this->context->videos->find($vid->id);
			$video->addSlug($video->label);
			$video->updateMetaData();
			$video->update();

			foreach ($v->categories as $cid) {
				$this->context->categories->find($cid)->addVideo($video);
			}
			foreach ($v->tags as $tid) {
				$video->addTag($tid);
			}

		} else { // edit
			if (!$this->user->isInrole(ROLE::EDITOR)) {
				throw new \Nette\Application\ForbiddenRequestException;
			}

			$vid = $this->video;
			$vid->label = $v->label;
			$vid->description = $v->description;
			$vid->author_id = $author_id;
			$vid->exercise_id = $v->exercise_id == 0 ? NULL : $v->exercise_id;
			$vid->external_exercise_url = $v->external_exercise_url;
			$vid->youtube_id = $v->youtube_id;
			$vid->updateMetaData();
			$vid->update();

			$vid->addSlug($v->label);

			$vid->updateTags($v['tags']);
			$vid->updateCategories($v['categories']);
		}

		// invalidate cache
		$invalid = ["videos", "video/$vid->id"];
		foreach ($v['categories'] as $cid) {
			$invalid[] = "category/$cid";
		}
		foreach ($v['tags'] as $tid) {
			$invalid[] = "tag/$tid";
		}
		if ($this->action === 'add') {
			$invalid[] = "videos/count";
		}
		$cache = new Cache($this->context->cacheStorage);
		$cache->clean([Cache::TAGS => $invalid]);


		$this->redirect('default', ['vid' => $vid->id]);
	}



	public function renderEdit()
	{
		if (!$this->user->isInrole(ROLE::EDITOR)) {
			throw new \Nette\Application\ForbiddenRequestException;
		}

		$form = $this['videoForm'];
		$vid = $this->video;

		$form['label']->setValue($vid->label);
		$form['description']->setValue($vid->description);
		$form['youtube_id']->setValue($vid->youtube_id);
		$form['tags']->setValue($vid->getTagsIds());
		$form['categories']->setValue($vid->getCategoryIds());
		$form['exercise_id']->setValue($vid->exercise_id);
		$form['author_id']->setValue($vid->author_id);
		$form['external_exercise_url']->setValue($vid->external_exercise_url);
	}



	public function actionEditSubtitles()
	{
		$url = urlencode("http://www.youtube.com/watch?v=" . $this->video->youtube_id);
		$video_id = $this->context->amara->getId($this->video);
		list($basePK, $subPK) = $this->context->amara->getLanguagePk($this->video);
		$target = "http://www.universalsubtitles.org/en/onsite_widget/?config=%7B%22videoID%22%3A%22$video_id%22%2C%22videoURL%22%3A%22$url%22%2C%22effectiveVideoURL%22%3A%22$url%22%2C%22languageCode%22%3A%22cs%22%2C%22originalLanguageCode%22%3Anull%2C%22subLanguagePK%22%3A$subPK%2C%22baseLanguagePK%22%3A$basePK%7D";
		$this->redirectUrl($target);
	}



	public function handleVerify()
	{
		if (!$this->user->isInrole(ROLE::VERIFIER)) {
			throw new \Nette\Application\ForbiddenRequestException;
		}

		$this->video->verify($this->user);
		$this->redirect('this');
	}



	public function handleUndoVerify()
	{
		if (!$this->user->isInrole(ROLE::VERIFIER)) {
			throw new \Nette\Application\ForbiddenRequestException;
		}

		$this->video->undoVerify($this->user);
		$this->redirect('this');
	}



	public function handleReloadSubtitles()
	{
		if (!$this->user->isInrole(ROLE::EDITOR)) {
			throw new \Nette\Application\ForbiddenRequestException;
		}

		$this->context->amara->clearCache($this->video);
		$this->flashMessage('Cache titulků byla obnovena.');
		$this->redirect('this');
	}

}
