<?php

class Video extends Entity
{
	
	/**
	 * @return Video
	 */
	public function getCategory()
	{
		return $this->context->categories->findOneBy(['id' => $this->category_id]);
	}

	
	
	/** 
	 * @return Video
	 */
	public function getNextVideo()
	{
		return $this->getAdjacentVideo(+1);
	}
	
	
	
	/** 
	 * @return Video
	 */
	public function getPreviousVideo()
	{
		return $this->getAdjacentVideo(-1);
	}
	
	
	
	/** 
	 * @return Video
	 */
	protected function getAdjacentVideo($offset)
	{
		return $this->context->videos->findOneBy(['category_id' => $this->category_id, 'position' => $this->position + $offset]);
	}
	
	
	
	/**
	 * @return Exercise
	 */
	public function getExercise()
	{
		return $this->context->exercises->findOneBy(['id' => $this->exercise_id]);
	}
	
	
	
	public function getMetaData()
	{
		$cache = new \Nette\Caching\Cache($this->context->cacheStorage, 'video');
		if (!isset($cache[$this->id])) {
			$res = file_get_contents("http://gdata.youtube.com/feeds/api/videos/$this->youtube_id?v=2&alt=jsonc&prettyprint=false");
			$data = \Nette\Utils\Json::decode($res);
			$cache->save($this->id, $data, [
				\Nette\Caching\Cache::EXPIRE => '+ 24 hours',
			]);
		}
		
		return $cache[$this->id];
	}
	
	
	
	public function getDuration()
	{
		return $this->getMetaData()->data->duration;
	}
	
}
