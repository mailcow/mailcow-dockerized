<?php

namespace OAuth2;

use OAuth2\Storage\Memory;
use PHPUnit\Framework\TestCase;

class ScopeTest extends TestCase
{
    public function testCheckScope()
    {
        $scopeUtil = new Scope();

        $this->assertFalse($scopeUtil->checkScope('invalid', 'list of scopes'));
        $this->assertTrue($scopeUtil->checkScope('valid', 'valid and-some other-scopes'));
        $this->assertTrue($scopeUtil->checkScope('valid another-valid', 'valid another-valid and-some other-scopes'));
        // all scopes must match
        $this->assertFalse($scopeUtil->checkScope('valid invalid', 'valid and-some other-scopes'));
        $this->assertFalse($scopeUtil->checkScope('valid valid2 invalid', 'valid valid2 and-some other-scopes'));
    }

    public function testScopeStorage()
    {
        $scopeUtil = new Scope();
        $this->assertEquals($scopeUtil->getDefaultScope(), null);

        $scopeUtil = new Scope(array(
            'default_scope' => 'default',
            'supported_scopes' => array('this', 'that', 'another'),
        ));
        $this->assertEquals($scopeUtil->getDefaultScope(), 'default');
        $this->assertTrue($scopeUtil->scopeExists('this that another', 'client_id'));

        $memoryStorage = new Memory(array(
            'default_scope' => 'base',
            'supported_scopes' => array('only-this-one'),
        ));
        $scopeUtil = new Scope($memoryStorage);

        $this->assertEquals($scopeUtil->getDefaultScope(), 'base');
        $this->assertTrue($scopeUtil->scopeExists('only-this-one', 'client_id'));
    }
}
