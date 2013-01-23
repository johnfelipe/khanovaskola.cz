<?php

namespace FrontModule;

use Nette\Application\UI\Form;
use Nette\Caching\Cache;


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

		if ($this->action === 'add') {
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



	public function renderDefault($autoplay = 0)
	{
		$this->template->autoplay = $autoplay;
		$this->template->video = $this->video;
		$this->template->category = $this->category;
	}



	public function renderAdd()
	{
		$this['videoForm']['categories']->setDefaultValue($this->getParam('categories'));
	}



	public function handleUpdateProgress($seconds)
	{
		if ($this->ajax && $this->user->loggedIn) {
			$this->user->entity->setProgress($this->video, $seconds, function() {
				// onVideoWatched
				$task = $this->user->entity->getTaskForVideo($this->video);
				if ($task && !$task->completed) {
					$cache = new Cache($this->context->cacheStorage);
					$cache->clean([Cache::TAGS => $this->task->getTagsToInvalidate()]);
					$task->setCompleted()->update();
				}
			});

			$cache = new Cache($this->context->cacheStorage);
			$cache->clean([Cache::TAGS => "watched/{$this->user->id}"]);

			$this->sendJson(['status' => 'success']);
		}

		$this->terminate();
	}




	public function createComponentVideoForm($name)
	{
		$form = $this->createForm($name);

		$form->addText('youtube_id', 'Youtube ID', NULL, 11); // every youtube_id has 11 chars
		$form->addText('label', 'Název');
		$form->addTextArea('description', 'Popis');
		$form->addMultiSelect('tags', 'Tagy', $this->context->tags->getFill());
		$form->addMultiSelect('categories', 'Kategorie', $this->context->categories->getFill());

		$form->addSubmit('send', 'Uložit');
	}



	public function onSuccessVideoForm($form)
	{
		$v = $form->values;

		if ($this->action === 'add') {
			if (!$this->user->isInrole(\NetteUser::ROLE_ADDER)) {
				throw new \Nette\Application\ForbiddenRequestException;
			}

			try {
				$vid = $this->context->videos->insert([
					'label' => $v->label,
					'description' => $v->description,
					'youtube_id' => $v->youtube_id
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
			if (!$this->user->isInrole(\NetteUser::ROLE_EDITOR)) {
				throw new \Nette\Application\ForbiddenRequestException;
			}

			$vid = $this->video;
			$vid->label = $v->label;
			$vid->description = $v->description;

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
		$cache = new Cache($this->context->cacheStorage);
		$cache->clean([Cache::TAGS => $invalid]);


		$this->redirect('default', ['vid' => $vid->id]);
	}



	public function renderEdit()
	{
		if (!$this->user->isInrole(\NetteUser::ROLE_EDITOR)) {
			throw new \Nette\Application\ForbiddenRequestException;
		}

		$form = $this['videoForm'];
		$vid = $this->video;

		$form['label']->setValue($vid->label);
		$form['description']->setValue($vid->description);
		$form['youtube_id']->setValue($vid->youtube_id);
		$form['tags']->setValue($vid->getTagsIds());
		$form['categories']->setValue($vid->getCategoryIds());
	}



	public function actionEditSubtitles()
	{
		$url = urlencode("http://www.youtube.com/watch?v=" . $this->video->youtube_id);
		$video_id = $this->context->amara->getId($this->video);
		list($basePK, $subPK) = $this->context->amara->getLanguagePk($this->video);
		$target = "http://www.universalsubtitles.org/en/onsite_widget/?config=%7B%22videoID%22%3A%22$video_id%22%2C%22videoURL%22%3A%22$url%22%2C%22effectiveVideoURL%22%3A%22$url%22%2C%22languageCode%22%3A%22cs%22%2C%22originalLanguageCode%22%3Anull%2C%22subLanguagePK%22%3A$subPK%2C%22baseLanguagePK%22%3A$basePK%7D";
		$this->redirectUrl($target);
	}

}
