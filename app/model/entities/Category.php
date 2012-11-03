<?php

/**
 * @property int	$parent_id
 * @property bool	$is_leaf
 * @property string	$label
 * @property string	$slug			Webalized $label
 * @property strign	$description
 * @property int	$position		Unique between siblings
 */
class Category extends Entity
{

	/**
	 * @return Category[]
	 */
	public function getSubCategories()
	{
		return $this->context->categories->findBy(['parent_id' => $this->id]);
	}



	/**
	 * @param bool $secondHalf Return the other half
	 * @return Category[]
	 */
	public function getSubCategoriesHalf($secondHalf = FALSE)
	{
		$split = ceil($this->getSubCategories()->count() / 2);

		if (!$secondHalf) {
			return $this->getSubCategories()->limit($split);

		} else {
			return $this->getSubCategories()->limit($split, $split);
		}
	}



	/**
	 * Recursive
	 * @return bool
	 */
	public function hasVideos()
	{
		$count = count($this->getVideoIds());
		if ($count) {
			return TRUE;
		}

		foreach ($this->getSubCategories() as $subcat) {
			if ($subcat->hasVideos()) {
				return TRUE;
			}
		}

		return FALSE;
	}



	/**
	 * @return Video[]
	 */
	public function getVideos()
	{
		return $this->context->videos->findByCategory($this);
	}



	/**
	 * @return Video[]
	 */
	public function getVideosHalf($secondHalf = FALSE)
	{
		$count = $this->getVideos()->count();
		if ($count > 10) {
			$split = ceil($count / 2);
		} else {
			$split = 999; // only one column if more than ten videos
		}

		if (!$secondHalf) {
			return $this->getVideos()->limit($split);

		} else {
			return $this->getVideos()->limit($split, $split);
		}
	}



	/**
	 * @return Category
	 */
	public function getParent()
	{
		return $this->context->categories->find($this->parent_id);
	}



	/**
	 * @return bool
	 */
	public function isSubject()
	{
		return $this->parent_id === NULL;
	}



	/**
	 * @return bool
	 */
	public function isLeaf()
	{
		return $this->is_leaf == 1;
	}



	/**
	 * @return bool
	 */
	public function isSubcategory()
	{
		return !$this->isSubject() && !$this->isLeaf();
	}



	/**
	 * @return int seconds
	 */
	public function getDuration()
	{
		$cache = new \Nette\Caching\Cache($this->context->cacheStorage, 'category_duration');
		if (!isset($cache[$this->id])) {
			$duration = 0;
			if ($this->isLeaf()) {
				foreach ($this->getVideos() as $video) {
					$duration += $video->getDuration();
				}
			} else {
				foreach ($this->getSubCategories() as $subcat) {
					$duration += $subcat->getDuration();
				}
			}

			$cache->save($this->id, $duration, [
				\Nette\Caching\Cache::TAGS => ["category/$this->id"],
			]);
		}

		return $cache[$this->id];
	}


	/**
	 * @return string
	 */
	public function getDescription()
	{
		$desc = '';

		$bread = [];
		$parent = $this;
		while ($parent) {
			$bread[] = $parent->label;
			$parent = $parent->getParent();
		}
		if (count($bread)) {
			$bread = array_reverse($bread);
			$desc .= implode(" ≫ ", $bread);
		}

		if ($this->description) {
			$desc .= ". {$this->description}";
		}

		if (strlen($desc) > 120) {
			return $desc;
		}

		if ($this->isLeaf()) {
			$videos = [];
			foreach ($this->getVideos() as $video) {
				$videos[] = $video->label;
			}
			if (count($videos)) {
				$desc .= ": " . implode(", ", $videos);
			}

		} else {
			$subcats = [];
			foreach ($this->getSubCategories() as $subcat) {
				$subcats[] = $subcat->label;
			}
			if (count($subcats)) {
				$desc .= ": " . implode(", ", $subcats);
			}
		}

		return trim($desc);
	}



	/**
	 * Ordered by position
	 * @return int[]
	 */
	public function getVideoIds()
	{
		$ids = [];
		foreach ($this->context->database->query('SELECT video_id FROM category_video WHERE category_id=? ORDER BY position ASC', $this->id) as $row) {
			$ids[] = (int) $row['video_id'];
		}
		return $ids;
	}



	public function containsVideo(Video $video)
	{
		foreach ($this->getVideoIds() as $id) {
			if ($id === $video->id) {
				return TRUE;
			}
		}

		return FALSE;
	}



	/**
	 * Ordered by position
	 * @return int[]
	 */
	public function getExerciseIds()
	{
		$ids = [];
		foreach ($this->context->database->query('SELECT exercise_id FROM category_exercise WHERE category_id=? ORDER BY position ASC', $this->id) as $row) {
			$ids[] = (int) $row['exercise_id'];
		}
		return $ids;
	}



	/**
	 * Recursive
	 * @return bool
	 */
	public function hasExercises()
	{
		$count = count($this->getExerciseIds());
		if ($count) {
			return TRUE;
		}

		foreach ($this->getSubCategories() as $subcat) {
			if ($subcat->hasExercises()) {
				return TRUE;
			}
		}

		return FALSE;
	}



	/**
	 * @return Exercise[]
	 */
	public function getExercises()
	{
		return $this->context->exercises->findByCategory($this);
	}

}
