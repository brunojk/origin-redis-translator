<?php

class AllTest extends TestAbstract
{
    public function tearDown() {
        $this->redis->flushDB();
        parent::tearDown();
    }

    public function testConnection() {
        $this->assertNotNull($this->redis);
    }

//    public function testValidationsDefault() {
//        $this->assertEquals('validation.custom.email.required', $this->trans('validation.custom.email.required'));
//        $this->assertEquals('The :attribute field is required.', $this->trans('validation.required'));
//    }

    public function testCountryLocale() {
        $this->redis->set('app.en.default.hello_world', 'Hello World');
        $this->redis->set('app.en-us.default.hello_world', 'Hello World us');

        $this->redis->set('app.pt.default.hello_world', 'Olá mundo');
        $this->redis->set('app.pt-br.default.hello_world', 'Olá mundo br');

        $this->redis->set('app.es.default.hello_world', 'Hola mondo');
        $this->redis->set('app.es-py.default.hello_world', 'Hola mondo py');


        $this->assertEquals('Hello World', $this->trans('hello_world'));
        $this->assertEquals('Hello World us', $this->trans('hello_world', [], null, 'en-us'));
        $this->assertEquals('Olá mundo', $this->trans('hello_world', [], null, 'pt'));
        $this->assertEquals('Olá mundo br', $this->trans('hello_world', [], null, 'pt-br'));
        $this->assertEquals('Hola mondo', $this->trans('hello_world', [], null, 'es'));
        $this->assertEquals('Hola mondo py', $this->trans('hello_world', [], null, 'es-py'));


        $this->redis->del('app.pt-br.default.hello_world');
        $this->assertEquals('Olá mundo', $this->trans('hello_world', [], null, 'pt-br'));
        $this->redis->del('app.es-py.default.hello_world');
        $this->assertEquals('Hola mondo', $this->trans('hello_world', [], null, 'es-py'));
    }

    public function testReplacements() {
        $this->redis->set('app.en.default.hello_world', 'Hello World, :name');
        $this->redis->set('app.pt.default.hello_world', 'Olá Mundo, :name');
        $this->redis->set('app.es.default.hello_world', 'Hola Mondo, :name');

        $this->assertEquals('Hello World, Bruno', $this->trans('hello_world', ['name' => 'Bruno']));
        $this->assertEquals('Olá Mundo, Bruno', $this->trans('hello_world', ['name' => 'Bruno'], null, 'pt'));
        $this->assertEquals('Hola Mondo, Bruno', $this->trans('hello_world', ['name' => 'Bruno'], null, 'es'));
        $this->assertEquals('Hello World, Bruno', $this->trans('hello_world', ['name' => 'Bruno'], null, 'ru'));

        $this->redis->del('app.en.default.hello_world');
        $this->redis->del('app.pt.default.hello_world');
        $this->redis->del('app.es.default.hello_world');
        $this->redis->set('plt.en.default.hello_world', 'Hello World, (plt) :name');
        $this->assertEquals('Hello World, (plt) Bruno', $this->trans('hello_world', ['name' => 'Bruno']));
    }

    public function testChoice() {
        $this->redis->set('app.en.default.hello_world', '{0} Hello World|[1,Inf] Hellos Worlds');

        $this->assertEquals('Hello World', $this->transChoice('hello_world', 0));
        $this->assertEquals('Hellos Worlds', $this->transChoice('hello_world', 1));
        $this->assertEquals('Hellos Worlds', $this->transChoice('hello_world', 2));
        $this->assertEquals('Hellos Worlds', $this->transChoice('hello_world', 2, [], null, 'es'));
        $this->assertEquals('Hellos Worlds', $this->transChoice('hello_world', 2, [], null, 'pt'));


        $this->redis->del('app.en.default.hello_world');
        $this->assertEquals('hello_world', $this->transChoice('hello_world', 2, [], null, 'es'));


        $this->redis->set('app.en.default.hello_world', 'Hello World');
        $this->assertEquals('Hello World', $this->transChoice('hello_world', 0));
        $this->assertEquals('Hello World', $this->transChoice('hello_world', 1));
        $this->assertEquals('Hello World', $this->transChoice('hello_world', 5));


        $this->redis->del('app.en.default.hello_world');
        $this->redis->set('app.pt.default.hello_world', 'Olá mundo');

        $this->assertEquals('hello_world', $this->transChoice('hello_world', 0));
        $this->assertEquals('hello_world', $this->transChoice('hello_world', 1));
        $this->assertEquals('hello_world', $this->transChoice('hello_world', 5));

        $this->assertEquals('hello_world', $this->transChoice('hello_world', 0, [], null, 'es'));
        $this->assertEquals('hello_world', $this->transChoice('hello_world', 1, [], null, 'es'));
        $this->assertEquals('hello_world', $this->transChoice('hello_world', 5, [], null, 'es'));

        $this->assertEquals('Olá mundo', $this->transChoice('hello_world', 0, [], null, 'pt'));
        $this->assertEquals('Olá mundo', $this->transChoice('hello_world', 1, [], null, 'pt'));
        $this->assertEquals('Olá mundo', $this->transChoice('hello_world', 5, [], null, 'pt'));
    }

    public function testCascadeOrigins() {
        $this->redis->set('plt.en.default.hello_world', 'Hello World plt');
        $this->redis->set('plt.pt.default.hello_world', 'Olá mundo plt');
        $this->redis->set('plt.es.default.hello_world', 'Hola mondo plt');

        $this->assertEquals('Hello World plt', $this->trans('hello_world'));
        $this->assertEquals('Olá mundo plt', $this->trans('hello_world', [], null, 'pt'));
        $this->assertEquals('Hola mondo plt', $this->trans('hello_world', [], null, 'es'));

        $this->redis->set('app.en.default.hello_world', 'Hello World');
        $this->assertEquals('Hello World', $this->trans('hello_world'));
        $this->assertNotEquals('Hello World', $this->trans('hello_world', [], null, 'pt'));
    }

    public function testCascadeLang() {
        $this->redis->set('app.en.default.hello_world', 'Hello World');
        $this->redis->set('app.pt.default.hello_world', 'Olá mundo');
        $this->redis->set('app.es.default.hello_world', 'Hola mondo');

        $this->assertEquals('Hello World', $this->trans('hello_world'));
        $this->assertEquals('Olá mundo', $this->trans('hello_world', [], null, 'pt'));
        $this->assertEquals('Hola mondo', $this->trans('hello_world', [], null, 'es'));

        $this->redis->del('app.pt.default.hello_world');
        $this->assertEquals('Hello World', $this->trans('hello_world', [], null, 'pt'));

        $this->redis->del('app.en.default.hello_world');
        $this->assertEquals('hello_world', $this->trans('hello_world'));
        $this->assertEquals('hello_world', $this->trans('hello_world', [], null, 'pt'));
        $this->assertNotEquals('hello_world', $this->trans('hello_world', [], null, 'es'));
    }

    public function testDefaultOrigins() {
        $this->redis->set('plt.en.default.hello_world', 'Hello World plt');
        $this->assertEquals('Hello World plt', $this->trans('hello_world'));
        $this->assertEquals('Hello World plt', $this->trans('default.hello_world'));
        $this->assertEquals('Hello World plt', $this->trans('hello_world', [], 'default'));

        $this->redis->set('app.en.default.hello_world', 'Hello World');
        $this->assertEquals('Hello World', $this->trans('hello_world'));
        $this->assertEquals('Hello World', $this->trans('default.hello_world'));
        $this->assertEquals('Hello World', $this->trans('hello_world', [], 'default'));
    }

    public function testDefault() {
        $this->redis->set('app.en.default.hello_world', 'Hello World');
        $this->assertEquals('Hello World', $this->trans('hello_world'));
        $this->assertEquals('Hello World', $this->trans('default.hello_world'));
        $this->assertEquals('Hello World', $this->trans('hello_world', [], 'default'));

        //update
        $this->redis->set('app.en.default.hello_world', 'Hello World Updated!');
        $this->assertEquals('Hello World Updated!', $this->trans('hello_world'));
        $this->assertEquals('Hello World Updated!', $this->trans('default.hello_world'));
        $this->assertEquals('Hello World Updated!', $this->trans('hello_world', [], 'default'));

        //delete
        $this->redis->del('app.en.default.hello_world');
        $this->assertEquals('hello_world', $this->trans('hello_world'));
        $this->assertEquals('default.hello_world', $this->trans('default.hello_world'));
        $this->assertEquals('default.hello_world', $this->trans('default.hello_world', [], 'outcome'));
        $this->assertNotEquals('default.hello_world', $this->trans('hello_world', [], 'outcome'));

        $this->redis->set('app.en.default.hello_world', 'Hello World!');
        $this->assertNotEquals('Hello World!', $this->trans('outcome.hello_world'));
        $this->assertEquals('Hello World!', $this->trans('default.hello_world', [], 'outcome'));
        $this->assertEquals('Hello World!', $this->trans('hello_world'));
    }
}
