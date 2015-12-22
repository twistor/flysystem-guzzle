<?php

namespace Twistor\Flysystem;

use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;
use Guzzle\Plugin\Mock\MockPlugin;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use Twistor\Flysystem\GuzzleAdapter;

/**
 * @coversDefaultClass \Twistor\Flysystem\GuzzleAdapter
 */
class GuzzleAdapterTest  extends \PHPUnit_Framework_TestCase
{
    /**
     * The HTTP adapter.
     *
     * @var \Twistor\Flysystem\GuzzleAdapter
     */
    protected $adapter;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->client = new Client();
        $this->adapter = new GuzzleAdapter('http://example.com', $this->client);
    }

    /**
     * @covers ::__construct
     * @covers ::getBaseUrl
     */
    public function testConstructor()
    {
        $adapter = new GuzzleAdapter('http://example.com/foo');
        $this->assertSame('http://example.com/foo/', $adapter->getBaseUrl());

        $adapter = new GuzzleAdapter('https://user:pass@example.com/foo');
        $this->assertSame('https://user:pass@example.com/foo/', $adapter->getBaseUrl());
    }

    /**
     * @covers ::copy
     */
    public function testCopy()
    {
        $this->assertFalse($this->adapter->copy('file.txt', 'other.txt'));
    }

    /**
     * @covers ::createDir
     */
    public function testCreateDir()
    {
        $this->assertFalse($this->adapter->createDir('file.txt/dir', new Config()));
    }

    /**
     * @covers ::delete
     */
    public function testDelete()
    {
        $this->assertFalse($this->adapter->delete('file.txt'));
    }

    /**
     * @covers ::deleteDir
     */
    public function testDeleteDir()
    {
        $this->assertFalse($this->adapter->deleteDir('dir'));
    }

    /**
     * @covers ::getMetaData
     * @covers ::getMimetype
     * @covers ::getSize
     * @covers ::getTimestamp
     */
    public function testGetMetadata()
    {
        $plugin = new MockPlugin();

        $plugin->addResponse(new Response(200, ['Content-Type' => 'image/jpeg; charset=utf-8', 'Content-Length' => 42, 'Last-Modified' => 'Wed, 15 Nov 1995 04:58:08 GMT']));
        $plugin->addResponse(new Response(200, ['Content-Type' => 'image/jpeg; charset=utf-8', 'Content-Length' => 42, 'Last-Modified' => 'Wed, 15 Nov 1995 04:58:08 GMT']));
        $plugin->addResponse(new Response(200, ['Content-Type' => 'image/jpeg; charset=utf-8', 'Content-Length' => 42, 'Last-Modified' => 'Wed, 15 Nov 1995 04:58:08 GMT']));
        $plugin->addResponse(new Response(200, ['Content-Type' => 'image/jpeg; charset=utf-8', 'Content-Length' => 42, 'Last-Modified' => 'Wed, 15 Nov 1995 04:58:08 GMT']));
        $plugin->addResponse(new Response(200));
        $plugin->addResponse(new Response(200));
        $plugin->addResponse(new Response(200));
        $plugin->addResponse(new Response(200));
        $plugin->addResponse(new Response(404));
        $plugin->addResponse(new Response(404));
        $plugin->addResponse(new Response(404));
        $plugin->addResponse(new Response(404));

        $this->client->addSubscriber($plugin);

        $response = [
            'type' => 'file',
            'path' => 'foo.jpg',
            'timestamp' => 816411488,
            'size' => 42,
            'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
            'mimetype' => 'image/jpeg',

        ];
        $this->assertSame($response, $this->adapter->getMetadata('foo.jpg'));
        $this->assertSame($response, $this->adapter->getMimetype('foo.jpg'));
        $this->assertSame($response, $this->adapter->getSize('foo.jpg'));
        $this->assertSame($response, $this->adapter->getTimestamp('foo.jpg'));

        $response = [
            'type' => 'file',
            'path' => 'foo.jpg',
            'timestamp' => 0,
            'size' => 0,
            'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
            'mimetype' => 'image/jpeg',
        ];
        $this->assertSame($response, $this->adapter->getMetadata('foo.jpg?bar=bazz#fizz'));
        $this->assertSame($response, $this->adapter->getMimetype('foo.jpg?bar=bazz#fizz'));
        $this->assertSame($response, $this->adapter->getSize('foo.jpg?bar=bazz#fizz'));
        $this->assertSame($response, $this->adapter->getTimestamp('foo.jpg?bar=bazz#fizz'));

        $this->assertFalse($this->adapter->getMetadata('foot.jpg'));
        $this->assertFalse($this->adapter->getMimetype('foot.jpg'));
        $this->assertFalse($this->adapter->getSize('foot.jpg'));
        $this->assertFalse($this->adapter->getTimestamp('foot.jpg'));
    }

    /**
     * @covers ::getVisibility
     */
    public function testGetVisibility()
    {
        $response = [
            'path' => 'foo.html',
            'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
        ];

        $this->assertSame($response, $this->adapter->getVisibility('foo.html'));

        $adapter = new GuzzleAdapter('https://user:pass@example.com/foo');

        $response = [
            'path' => 'foo.html',
            'visibility' => AdapterInterface::VISIBILITY_PRIVATE,
        ];

        $this->assertSame($response, $adapter->getVisibility('foo.html'));
    }

    /**
     * @covers ::has
     */
    public function testHas()
    {
        $plugin = new MockPlugin();

        $plugin->addResponse(new Response(200));
        $plugin->addResponse(new Response(404));
        $plugin->addResponse(new Response(202));

        $this->client->addSubscriber($plugin);

        $this->assertTrue($this->adapter->has('foo.html'));
        $this->assertFalse($this->adapter->has('foo.html'));
        $this->assertFalse($this->adapter->has('foo.html'));
    }

    /**
     * @covers ::listContents
     */
    public function testListContents()
    {
        $this->assertSame([], $this->adapter->listContents('dir'));
    }

    /**
     * @covers ::read
     */
    public function testRead()
    {
        $plugin = new MockPlugin();

        $plugin->addResponse(new Response(200, [], 'foo'));
        $plugin->addResponse(new Response(404));

        $this->client->addSubscriber($plugin);

        $response = $this->adapter->read('test.html');
        $this->assertSame($response['path'], 'test.html');
        $this->assertSame('foo', $response['contents']);

        $this->assertFalse($this->adapter->read('bar.html'));

        // Test stream_get_contents() returns false;
        $adapter = $this->getMockBuilder('Twistor\Flysystem\GuzzleAdapter')
                        ->setConstructorArgs(['http://example.com'])
                        ->setMethods(['readStream'])
                        ->getMock();

        $adapter->method('readStream')
                ->will($this->returnValue(['stream' => false]));

        $this->assertFalse($adapter->read('foo.html'));

    }

    /**
     * @covers ::readStream
     */
    public function testReadStream()
    {
        $plugin = new MockPlugin();

        $plugin->addResponse(new Response(200, [], 'foo'));
        $plugin->addResponse(new Response(404));

        $this->client->addSubscriber($plugin);

        $response = $this->adapter->readStream('test.html');
        $this->assertSame($response['path'], 'test.html');
        $this->assertSame('foo', stream_get_contents($response['stream']));

        $this->assertFalse($this->adapter->readStream('bar.html'));
    }

    /**
     * @covers ::rename
     */
    public function testRename()
    {
        $this->assertFalse($this->adapter->rename('file.txt', 'new_file.txt'));
    }

    /**
     * @covers ::setVisibility
     */
    public function testSetVisibility()
    {
        $response = [
            'path' => 'foo.html',
            'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
        ];

        $this->assertSame($response, $this->adapter->setVisibility('foo.html', AdapterInterface::VISIBILITY_PUBLIC));
        $this->assertFalse($this->adapter->setVisibility('foo.html', AdapterInterface::VISIBILITY_PRIVATE));
    }

    /**
     * @covers ::update
     */
    public function testUpdate()
    {
        $this->assertFalse($this->adapter->update('file.txt', 'contents', new Config()));
    }

    /**
     * @covers ::updateStream
     */
    public function testUpdateStream()
    {
        $this->assertFalse($this->adapter->updateStream('file.txt', 'contents', new Config()));
    }

    /**
     * @covers ::write
     */
    public function testWrite()
    {
        $this->assertFalse($this->adapter->write('file.txt', 'contents', new Config()));
    }

    /**
     * @covers ::writeStream
     */
    public function testWriteStream()
    {
        $this->assertFalse($this->adapter->writeStream('file.txt', 'contents', new Config()));
    }
}

function stream_get_contents($handle)
{
    if ($handle === false) {
        return $handle;
    }

    return \stream_get_contents($handle);
}

