<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Controllers\Admin\SettingController;
use App\Core\Database;
use App\Core\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * The settings form is the only place a value can be written site-wide, so its
 * save path needs the same care as a payment. These cases pin the behaviour that
 * a partial submit must never erase the fields it did not mention: a bare POST
 * once blanked 29 of the 32 rows, which is what made the brand logo and the home
 * page copy disappear from the storefront.
 */
#[CoversClass(SettingController::class)]
final class SettingsSaveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Database::execute('delete from settings');
        Settings::forgetCache();
    }

    protected function tearDown(): void
    {
        Database::execute('delete from settings');
        Settings::forgetCache();

        parent::tearDown();
    }

    public function test_a_full_save_writes_the_posted_values(): void
    {
        $response = (new SettingController())->update($this->request('POST', 'admin/settings', [
            'brand.name' => 'Probe Brand',
            'whatsapp.number' => '8801799999999',
            'announcement.active' => '0',
            'trust.delivery_minutes' => '15',
        ]));

        self::assertSame(302, $response->status());

        Settings::forgetCache();
        self::assertSame('Probe Brand', Settings::get('brand.name'));
        self::assertSame('8801799999999', Settings::get('whatsapp.number'));
        self::assertFalse(Settings::bool('announcement.active'));
        self::assertSame(15, Settings::int('trust.delivery_minutes'));
    }

    public function test_an_unposted_key_keeps_its_value(): void
    {
        Settings::put('brand.name', 'Keep Me');
        Settings::put('hero.title', 'Keep This Headline');
        Settings::forgetCache();

        (new SettingController())->update($this->request('POST', 'admin/settings', ['brand.name' => 'Changed']));

        Settings::forgetCache();
        self::assertSame('Changed', Settings::get('brand.name'));
        self::assertSame('Keep This Headline', Settings::get('hero.title'), 'A partial submit must not blank a field it never saw.');
    }

    public function test_a_key_deliberately_cleared_is_stored_empty(): void
    {
        Settings::put('brand.tagline', 'Was here');
        Settings::forgetCache();

        // Submitted, but empty: that is the admin pressing "clear", not a
        // missing field, so it has to be honoured.
        (new SettingController())->update($this->request('POST', 'admin/settings', ['brand.tagline' => '']));

        Settings::forgetCache();
        self::assertSame('', (string) Database::selectOne('select value from settings where `key` = :k', [':k' => 'brand.tagline'])['value']);
    }

    public function test_a_bare_post_cannot_erase_the_settings_table(): void
    {
        foreach (['brand.name' => 'Shop', 'brand.logo' => 'img/dexxoxxbd.jpeg', 'hero.title' => 'Headline'] as $key => $value) {
            Settings::put($key, $value);
        }
        Settings::forgetCache();

        (new SettingController())->update($this->request('POST', 'admin/settings', ['_token' => 'not-a-real-token']));

        Settings::forgetCache();
        $empties = (int) Database::selectOne('select count(*) as total from settings where value = \'\' or value is null')['total'];

        self::assertSame(0, $empties, 'Nothing was submitted, so nothing may be overwritten.');
        self::assertSame('img/dexxoxxbd.jpeg', Settings::get('brand.logo'));
    }

    public function test_an_unknown_field_is_refused_rather_than_stored(): void
    {
        (new SettingController())->update($this->request('POST', 'admin/settings', ['app_key' => 'stolen', 'brand.name' => 'Shop']));

        Settings::forgetCache();
        self::assertNull(Database::selectOne('select `key` from settings where `key` = :k', [':k' => 'app_key']));
        self::assertSame('Shop', Settings::get('brand.name'));
    }
}
