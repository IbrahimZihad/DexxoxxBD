<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    /** @param array<string, string> $rules @param array<string, string> $labels */
    private function check(array $data, array $rules, array $labels = []): Validator
    {
        return new Validator($data, $rules, $labels);
    }

    public function test_required_and_email_rules(): void
    {
        $validator = $this->check(
            ['email' => '   ', 'name' => ''],
            ['email' => 'required|email', 'name' => 'required|min:2']
        );

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('email', $validator->errors());
        self::assertArrayHasKey('name', $validator->errors());
    }

    public function test_a_valid_pair_passes_and_is_trimmed(): void
    {
        $validator = $this->check(
            ['email' => '  Rahim.Bhai@Example.COM ', 'name' => ' Rahim '],
            ['email' => 'required|email', 'name' => 'required|min:2|max:120']
        );

        self::assertTrue($validator->passes(), $validator->firstMessage());

        $clean = $validator->validated();

        self::assertSame('Rahim.Bhai@Example.COM', trim((string) $clean['email']));
        self::assertSame('Rahim', (string) $clean['name']);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function phoneProvider(): array
    {
        return [
            'local format' => ['01712345678', true],
            'spaced' => ['01712 345678', true],
            'dashed' => ['01712-345678', true],
            'parenthesised' => ['(01712) 345678', true],
            'plus country code' => ['+8801712345678', true],
            'bare country code' => ['8801712345678', true],
            'operator digit too low' => ['01012345678', false],
            'too short' => ['0171234567', false],
            'too long' => ['017123456789', false],
            'landline only' => ['0255001234', false],
            'empty' => ['', false],
            'letters' => ['01712abcd', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phoneProvider')]
    public function test_phone_bd_accepts_only_real_bangladeshi_mobiles(string $value, bool $expected): void
    {
        $validator = $this->check(['phone' => $value], ['phone' => 'required|phone_bd']);

        self::assertSame($expected, $validator->passes(), $value . ' should ' . ($expected ? '' : 'not ') . 'validate');
    }

    public function test_phone_bd_validates_regardless_of_the_separators_typed(): void
    {
        $validator = $this->check(['phone' => '(01712) 345-678'], ['phone' => 'phone_bd']);

        self::assertTrue($validator->passes());

        // Deliberate limitation, and the reason CheckoutController tidies the
        // value before storing it: the rule normalises the copy it validates
        // against, while validated() hands back what the customer typed.
        self::assertSame('(01712) 345-678', (string) $validator->validated()['phone']);
        self::assertSame('01712345678', (string) preg_replace('/[\s()-]/', '', (string) $validator->validated()['phone']));
    }

    public function test_in_rule_is_the_payment_rail_whitelist(): void
    {
        $allowed = 'required|in:bkash,nagad,rocket,bank';

        self::assertTrue($this->check(['method' => 'nagad'], ['method' => $allowed])->passes());
        self::assertFalse($this->check(['method' => 'paypal'], ['method' => $allowed])->passes());
        self::assertFalse($this->check(['method' => 'bkash; drop table orders'], ['method' => $allowed])->passes());
    }

    public function test_numeric_bounds_and_integers(): void
    {
        self::assertTrue($this->check(['qty' => '3'], ['qty' => 'integer|between:1,10'])->passes());
        self::assertFalse($this->check(['qty' => '3.5'], ['qty' => 'integer'])->passes());
        self::assertFalse($this->check(['qty' => '11'], ['qty' => 'between:1,10'])->passes());
        self::assertFalse($this->check(['qty' => '0'], ['qty' => 'between:1,10'])->passes());
    }

    public function test_regex_rule_blocks_markup_in_free_text(): void
    {
        $rule = 'regex:/^[^<>]*$/';

        self::assertTrue($this->check(['note' => 'send to my Gmail account'], ['note' => $rule])->passes());
        self::assertFalse($this->check(['note' => '<script>alert(1)</script>'], ['note' => $rule])->passes());
    }

    public function test_max_rule_counts_characters(): void
    {
        self::assertTrue($this->check(['note' => str_repeat('a', 500)], ['note' => 'max:500'])->passes());
        self::assertFalse($this->check(['note' => str_repeat('a', 501)], ['note' => 'max:500'])->passes());
    }

    public function test_errors_are_keyed_by_field_and_hold_lists(): void
    {
        $validator = $this->check(['email' => 'nope'], ['email' => 'required|email|max:10']);

        $errors = $validator->errors();

        self::assertArrayHasKey('email', $errors);
        self::assertIsArray($errors['email']);
        self::assertNotEmpty($errors['email']);
        self::assertIsString($errors['email'][0]);
    }

    public function test_labels_appear_in_the_message(): void
    {
        // phone_bd has its own fixed wording (it needs to show the format), so
        // this checks the rules that do interpolate the field label.
        $validator = $this->check(['phone' => ''], ['phone' => 'required'], ['phone' => 'WhatsApp number']);

        self::assertTrue($validator->fails());
        self::assertStringContainsString('WhatsApp number', $validator->firstMessage());

        $unlabelled = $this->check(['whatsapp_number' => ''], ['whatsapp_number' => 'required']);

        self::assertStringContainsString('Whatsapp number', $unlabelled->firstMessage());
    }

    public function test_confirmed_rule_requires_the_matching_pair(): void
    {
        self::assertTrue($this->check(
            ['password' => 'secret123', 'password_confirmation' => 'secret123'],
            ['password' => 'required|min:8|confirmed']
        )->passes());

        self::assertFalse($this->check(
            ['password' => 'secret123', 'password_confirmation' => 'secret124'],
            ['password' => 'required|min:8|confirmed']
        )->passes());
    }
}
