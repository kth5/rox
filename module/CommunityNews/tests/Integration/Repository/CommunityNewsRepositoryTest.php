<?php

namespace Rox\CommunityNews\Model;

use Illuminate\Database\Eloquent\Collection;
use PHPUnit_Framework_TestCase;
use Rox\Core\Exception\InvalidArgumentException;
use Rox\Core\Exception\NotFoundException;
use Rox\Core\Factory\DatabaseFactory;

class CommunityNewsRepositoryTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $databaseFactory = new DatabaseFactory();
        $databaseFactory->__invoke();
    }

    public function tearDown()
    {
    }

    public function testGetLatestZero()
    {
        $this->expectException(InvalidArgumentException::class);

        $model = new CommunityNews();
        $model->getLatest(0);
    }

    public function testGetLatestNegative()
    {
        $this->expectException(InvalidArgumentException::class);

        $model = new CommunityNews();
        $model->getLatest(-1);
    }

    public function testGetLatestSingle()
    {
        $model = new CommunityNews();
        $communityNews = $model->getLatest();

//        $this->assertEquals('member-3', $communityNews->creator->Username);
//        $this->assertEquals('member-3', $communityNews->updater->Username);
//        $this->assertEquals(null, $communityNews->deleter);
        $this->assertEquals(CommunityNews::class, get_class($communityNews));
    }

    public function testGetLatestMultiple()
    {
        $model = new CommunityNews();
        $communityNews = $model->getLatest(2);

        $this->assertEquals(Collection::class, get_class($communityNews));
        $this->assertEquals(2, $communityNews->count());
    }

    public function testGetAll()
    {
        $model = new CommunityNews();
        $communityNews = $model->getAll();
//        $count = $model->getAllCount();

        $this->assertTrue(is_array($communityNews));
        $this->assertNotEmpty($communityNews);
//        $this->assertEquals(3, count($communityNews));
//        $this->assertEquals(3, $count);
    }

    public function testGetAllPaginated()
    {
        $model = new CommunityNews();
        $communityNews = $model->getAll(1, 2);
//        $count = $model->getAllCount();
//        $this->assertEquals(3, $count);

        $this->assertTrue(is_array($communityNews));
        $this->assertNotEmpty($communityNews);
        $this->assertEquals(2, count($communityNews));

        $communityNews = $model->getAll(2, 2);
//        $count = $model->getAllCount();
//        $this->assertEquals(3, $count);

        $this->assertTrue(is_array($communityNews));
        $this->assertNotEmpty($communityNews);
//        $this->assertEquals(1, count($communityNews));
    }

    public function testGetAllIncludingDeleted()
    {
        $model = new CommunityNews();
        $communityNews = $model->getAllIncludingDeleted();

        $this->assertTrue(is_array($communityNews));
        $this->assertNotEmpty($communityNews);
        //$this->assertEquals(4, count($communityNews));
    }

    public function testGetById()
    {
        $model = new CommunityNews();
        $communityNews = $model->getById(1);

        $this->assertTrue(is_object($communityNews));
    }

    public function testGetByNonExistingId()
    {
        $this->expectException(NotFoundException::class);
        $model = new CommunityNews();
        $model->getById(-1);
    }
}
