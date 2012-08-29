<?php

use Nette\Caching\Cache;


class Videos extends Table
{

	public function updatePositions(array $data)
	{
        throw new \Nette\NotImplementedException;
	}



	/**
	 * @param array $by
	 * @return Video[]
	 */
	public function findBy(array $by)
	{
		return $this->getTable()->where($by);
	}


	/**
	 * @param array $columns
	 * @param $query
	 * @return Video[]
	 */
	public function findInAny(array $columns, $query)
	{
		$filters = [];
		$args = [];
		foreach ($columns as $col) {
			$filters[] = "$col LIKE ?";
			$args[] = "%$query%";
		}

		$query = $this->getTable()->where(implode(" OR ", $filters), $args);
		return $query;
	}



	/**
	 * @param Tag $tag
	 * @return Video[]
	 */
	public function findByTag(Tag $tag)
	{
		return $this->getTable()->where('id', $tag->getVideosIds());
	}



	/**
	 * @return Video
	 */
	public function findRandom()
	{
		return $this->getTable()->select('*')->order('Rand()')->limit(1)->fetch();
	}


	/**
	 * @param Exercise $exercise
	 * @return Video[]
	 */
	public function findByExercise(Exercise $exercise)
	{
		return $this->findBy(['exercise_id' => $exercise->id]);
	}



	public function trimYoutubeIds()
	{
		return $this->connection->exec("UPDATE video SET youtube_id = Trim(youtube_id)");
	}



	public function addDubbedTagToVideos()
	{
		$tag = $this->context->tags->findDubTag();
		if (!$tag) {
			return FALSE;
		}

		$count = 0;
		foreach ($this->findAll() as $video) {
			if ($video->getMetaData()->data->uploader === 'khanacademyczech') {
				$video->addTag($tag->id);
				$count++;
			}
		}

		return $count;
	}


    /**
     * @return Video
     */
    public function findEmpty()
    {
        return $this->findOneBy([
            'label' => '',
            'description' => '',
        ]);
    }


    /**
     * @param Category $category
     * @return Video[]
     */
    public function findByCategory(Category $category)
    {
        return $this->findBy([
            'id' => $category->getVideoIds(),
            'slug <> ?' => '', // not empty videos
        ]);
    }

}
