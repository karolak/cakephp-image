<?php
namespace Karolak\Image\Tests\View\Helper;

use Cake\TestSuite\TestCase;
use Cake\View\View;
use Karolak\Image\View\Helper\ImageHelper;

class ImageHelperTest extends TestCase
{
    public function testObject() {
        $this->assertInstanceOf(ImageHelper::class, new ImageHelper(new View()));
    }
}
