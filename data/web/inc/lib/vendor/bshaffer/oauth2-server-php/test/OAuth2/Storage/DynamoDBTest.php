<?php

namespace OAuth2\Storage;

class DynamoDBTest extends BaseTest
{
    public function testGetDefaultScope()
    {
        $client = $this->getMockBuilder('\Aws\DynamoDb\DynamoDbClient')
            ->disableOriginalConstructor()
            ->setMethods(array('query'))
            ->getMock();

        $return = $this->getMockBuilder('\Guzzle\Service\Resource\Model')
            ->setMethods(array('count', 'toArray'))
            ->getMock();

        $data = array(
            'Items' => array(),
            'Count' => 0,
            'ScannedCount'=> 0
        );

        $return->expects($this->once())
            ->method('count')
            ->will($this->returnValue(count($data)));

        $return->expects($this->once())
            ->method('toArray')
            ->will($this->returnValue($data));

        // should return null default scope if none is set in database
        $client->expects($this->once())
            ->method('query')
            ->will($this->returnValue($return));

        $storage = new DynamoDB($client);
        $this->assertNull($storage->getDefaultScope());
    }
}
