<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Tests\Provider;

use Imagine\Image\Box;
use Sonata\MediaBundle\Provider\ImageProvider;
use Sonata\MediaBundle\Tests\Entity\Media;
use Sonata\MediaBundle\Thumbnail\FormatThumbnail;

class ImageProviderTest extends AbstractProviderTest
{
    public function getProvider($allowedExtensions = array(), $allowedMimeTypes = array())
    {
        $resizer = $this->createMock('Sonata\MediaBundle\Resizer\ResizerInterface');
        $resizer->expects($this->any())->method('resize')->will($this->returnValue(true));
        $resizer->expects($this->any())->method('getBox')->will($this->returnValue(new Box(100, 100)));

        $adapter = $this->createMock('Gaufrette\Adapter');

        $filesystem = $this->getMockBuilder('Gaufrette\Filesystem')
            ->setMethods(array('get'))
            ->setConstructorArgs(array($adapter))
            ->getMock();
        $file = $this->getMockBuilder('Gaufrette\File')
            ->setConstructorArgs(array('foo', $filesystem))
            ->getMock();
        $filesystem->expects($this->any())->method('get')->will($this->returnValue($file));

        $cdn = new \Sonata\MediaBundle\CDN\Server('/uploads/media');

        $generator = new \Sonata\MediaBundle\Generator\DefaultGenerator();

        $thumbnail = new FormatThumbnail('jpg');

        $size = $this->createMock('Imagine\Image\BoxInterface');
        $size->expects($this->any())->method('getWidth')->will($this->returnValue(100));
        $size->expects($this->any())->method('getHeight')->will($this->returnValue(100));

        $image = $this->createMock('Imagine\Image\ImageInterface');
        $image->expects($this->any())->method('getSize')->will($this->returnValue($size));

        $adapter = $this->createMock('Imagine\Image\ImagineInterface');
        $adapter->expects($this->any())->method('open')->will($this->returnValue($image));

        $metadata = $this->createMock('Sonata\MediaBundle\Metadata\MetadataBuilderInterface');

        $provider = new ImageProvider('file', $filesystem, $cdn, $generator, $thumbnail, $allowedExtensions, $allowedMimeTypes, $adapter, $metadata);
        $provider->setResizer($resizer);

        return $provider;
    }

    public function testProvider()
    {
        $provider = $this->getProvider();

        $media = new Media();
        $media->setName('test.png');
        $media->setProviderReference('ASDASDAS.png');
        $media->setId(1023456);
        $media->setContext('default');

        $this->assertSame('default/0011/24/ASDASDAS.png', $provider->getReferenceImage($media));

        $this->assertSame('default/0011/24', $provider->generatePath($media));
        $this->assertSame('/uploads/media/default/0011/24/thumb_1023456_big.png', $provider->generatePublicUrl($media, 'big'));
        $this->assertSame('/uploads/media/default/0011/24/ASDASDAS.png', $provider->generatePublicUrl($media, 'reference'));

        $this->assertSame('default/0011/24/ASDASDAS.png', $provider->generatePrivateUrl($media, 'reference'));
        $this->assertSame('default/0011/24/thumb_1023456_big.png', $provider->generatePrivateUrl($media, 'big'));
    }

    public function testHelperProperies()
    {
        $provider = $this->getProvider();

        $provider->addFormat('admin', array('width' => 100));
        $media = new Media();
        $media->setName('test.png');
        $media->setProviderReference('ASDASDAS.png');
        $media->setId(10);
        $media->setHeight(100);
        $media->setContext('default');

        $properties = $provider->getHelperProperties($media, 'admin');

        $this->assertInternalType('array', $properties);
        $this->assertSame('test.png', $properties['title']);
        $this->assertSame(100, $properties['width']);

        $properties = $provider->getHelperProperties($media, 'admin', array(
            'width' => 150,
        ));

        $this->assertSame(150, $properties['width']);
    }

    public function testThumbnail()
    {
        $provider = $this->getProvider();

        $media = new Media();
        $media->setName('test.png');
        $media->setProviderReference('ASDASDAS.png');
        $media->setId(1023456);
        $media->setContext('default');

        $this->assertTrue($provider->requireThumbnails($media));

        $provider->addFormat('big', array('width' => 200, 'height' => 100, 'constraint' => true));

        $this->assertNotEmpty($provider->getFormats(), '::getFormats() return an array');

        $provider->generateThumbnails($media);

        $this->assertSame('default/0011/24/thumb_1023456_big.png', $provider->generatePrivateUrl($media, 'big'));
    }

    public function testEvent()
    {
        $provider = $this->getProvider();

        $provider->addFormat('big', array('width' => 200, 'height' => 100, 'constraint' => true));

        $file = new \Symfony\Component\HttpFoundation\File\File(realpath(__DIR__.'/../fixtures/logo.png'));

        $media = new Media();
        $media->setBinaryContent($file);
        $media->setId(1023456);

        // pre persist the media
        $provider->transform($media);
        $provider->prePersist($media);

        $this->assertSame('logo.png', $media->getName(), '::getName() return the file name');
        $this->assertNotNull($media->getProviderReference(), '::getProviderReference() is set');

        // post persit the media
        $provider->postPersist($media);

        $provider->postRemove($media);
    }

    public function testTransformFormatNotSupported()
    {
        $provider = $this->getProvider();

        $file = new \Symfony\Component\HttpFoundation\File\File(realpath(__DIR__.'/../fixtures/logo.png'));

        $media = new Media();
        $media->setBinaryContent($file);

        $this->assertNull($provider->transform($media));
        $this->assertNull($media->getWidth(), 'Width staid null');
    }
}
