<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\SettingRepository;
use App\Security\Sanitizer;

final class SettingController extends Controller
{
    public function publicIndex(): Response
    {
        return $this->json(['settings' => SettingRepository::all(true)]);
    }

    public function adminIndex(): Response
    {
        $values = SettingRepository::all(false);
        $groups = [];

        foreach (SettingRepository::definitions() as $row) {
            $group = (string) $row['group_name'];

            if (!isset($groups[$group])) {
                $groups[$group] = [];
            }

            $groups[$group][] = [
                'key' => (string) $row['key_name'],
                'label' => (string) $row['label'],
                'type' => (string) $row['type'],
                'is_public' => (int) $row['is_public'] === 1,
                'value' => $values[$row['key_name']] ?? $row['value'],
            ];
        }

        return $this->json(['settings' => $values, 'groups' => $groups]);
    }

    public function update(): Response
    {
        $submitted = $this->request->body();

        if ($submitted === []) {
            throw new HttpException(422, 'Nothing to save.');
        }

        $clean = [];

        foreach ($submitted as $key => $value) {
            $name = Sanitizer::key($key);

            if ($name === '') {
                continue;
            }

            $clean[$name] = is_array($value) ? $value : Sanitizer::text($value, 200);
        }

        $written = SettingRepository::setMany($clean);

        if ($written === 0) {
            throw new HttpException(422, 'None of those settings exist. Add them with a migration first.');
        }

        return $this->json(['ok' => true, 'saved' => $written, 'settings' => SettingRepository::all(false)]);
    }
}
