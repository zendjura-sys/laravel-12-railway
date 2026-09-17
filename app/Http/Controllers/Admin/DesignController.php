<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\DesignSettings;
use App\Support\FamilyContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Раздел «Дизайн» в админке.
 *
 * Пришёл на смену Design-пакетам (аддонам типа theme): для сайта на
 * Vue 3 со сборкой через Vite тема в ZIP-архиве бессмысленна — она не
 * может влезть в скомпилированные компоненты. Здесь настраивается то,
 * что действительно меняется без деплоя: бренд, параметры оформления и
 * содержание разделов главной.
 */
class DesignController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Design/Index', [
            'brand' => [
                'siteName' => Setting::get('site_name') ?: '',
                'siteTagline' => Setting::get('site_tagline') ?: '',
                'logoUrl' => DesignSettings::assetUrl('design_logo'),
                'faviconUrl' => DesignSettings::assetUrl('design_favicon'),
            ],
            'theme' => [
                'accent' => DesignSettings::accent(),
                'effects' => DesignSettings::effects(),
            ],
            'effectLevels' => DesignSettings::EFFECT_LEVELS,
            'content' => [
                'leadership' => FamilyContent::leadership(),
                'positions' => FamilyContent::positions(),
                'directions' => FamilyContent::directions(),
                'promotionCriteria' => FamilyContent::promotionCriteria(),
                'about' => FamilyContent::about(),
            ],
        ]);
    }

    public function updateBrand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'siteName' => ['nullable', 'string', 'max:120'],
            'siteTagline' => ['nullable', 'string', 'max:255'],
            // svg сознательно НЕ принимаем: это исполняемый в браузере
            // документ, а логотип вставляется в каждую страницу сайта.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            // У фавикона правило file, а не image: правило image знает
            // только jpg/jpeg/png/gif/bmp/webp, поэтому вместе с ним ни
            // один .ico не проходил — а форма его предлагает. Проверку
            // типа делает mimes: он смотрит на настоящий MIME, так что
            // переименованный скрипт всё равно не пройдёт.
            'favicon' => ['nullable', 'file', 'mimes:png,webp,ico', 'max:512'],
            'removeLogo' => ['nullable', 'boolean'],
            'removeFavicon' => ['nullable', 'boolean'],
        ]);

        Setting::set('site_name', $data['siteName'] ?? null, 'general');
        Setting::set('site_tagline', $data['siteTagline'] ?? null, 'general');

        foreach (['logo' => 'design_logo', 'favicon' => 'design_favicon'] as $field => $key) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store(DesignSettings::BRAND_DIR, 'public');
                DesignSettings::replaceAsset($key, $path);
            } elseif ($request->boolean('remove'.ucfirst($field))) {
                DesignSettings::replaceAsset($key, null);
            }
        }

        return back()->with('status', 'Бренд збережено.');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'effects' => ['required', 'in:'.implode(',', DesignSettings::EFFECT_LEVELS)],
        ]);

        Setting::set('design_accent', strtoupper($data['accent']), 'design');
        Setting::set('design_effects', $data['effects'], 'design');

        return back()->with('status', 'Оформлення збережено.');
    }

    public function updateContent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leadership' => ['present', 'array', 'max:24'],
            'leadership.*.title' => ['required', 'string', 'max:80'],
            'leadership.*.text' => ['nullable', 'string', 'max:400'],
            'leadership.*.nickname' => ['nullable', 'string', 'max:40'],

            'positions' => ['present', 'array', 'max:24'],
            // key форма не показує — воно просто їде транзитом разом із
            // рештою полів рядка (FamilyContent::preparePositions сама
            // генерує ключ, якщо його немає). Без правила тут
            // $request->validate() мовчки вирізав би поле з даних.
            'positions.*.key' => ['nullable', 'string', 'max:80'],
            'positions.*.title' => ['required', 'string', 'max:80'],
            'positions.*.text' => ['nullable', 'string', 'max:400'],
            'positions.*.image' => ['nullable', 'string', 'max:200'],

            'directions' => ['present', 'array', 'max:12'],
            'directions.*.title' => ['required', 'string', 'max:80'],
            'directions.*.tag' => ['nullable', 'string', 'max:80'],
            'directions.*.text' => ['nullable', 'string', 'max:400'],
            'directions.*.image' => ['nullable', 'string', 'max:200'],

            'promotionCriteria' => ['present', 'array', 'max:20'],
            'promotionCriteria.*' => ['nullable', 'string', 'max:160'],

            'about' => ['present', 'array', 'max:10'],
            'about.*' => ['nullable', 'string', 'max:600'],
        ]);

        // Ключі посад, які зникнуть після збереження (видалені рядки або
        // перейменування без явного key — таке трапляється, якщо форму
        // подають напряму, минаючи Vue). Рахуємо ДО save(): FamilyContent
        // сама генерує нові ключі для безключових рядків, і після
        // збереження різницю вже не побачити.
        $before = FamilyContent::positionKeys();

        FamilyContent::save('leadership', $data['leadership']);
        FamilyContent::save('positions', $data['positions']);
        FamilyContent::save('directions', $data['directions']);
        FamilyContent::save('promotion_criteria', $data['promotionCriteria']);
        FamilyContent::save('about', $data['about']);

        $after = FamilyContent::positionKeys();
        $removed = array_diff($before, $after);

        if ($removed !== []) {
            $affected = User::whereIn('position_key', $removed)->count();

            if ($affected > 0) {
                // Це не помилка збереження — воно вже відбулось. Лише
                // чесне попередження: посаду прибрали з переліку, і людей,
                // яким вона стояла, тепер треба перепризначити вручну
                // (Учасники), інакше в них "не призначено".
                return back()->with(
                    'status',
                    "Зміст збережено. Але посаду прибрано зі списку, і {$affected} ".
                    self::pluralizeMembers($affected).' лишились без призначеної посади — '.
                    'перепризначте їх в «Учасники».',
                );
            }
        }

        return back()->with('status', 'Зміст збережено. Сайт і бот оновилися одночасно.');
    }

    private static function pluralizeMembers(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 'учасник';
        }

        if (in_array($mod10, [2, 3, 4], true) && ! in_array($mod100, [12, 13, 14], true)) {
            return 'учасники';
        }

        return 'учасників';
    }

    /** Вернуть один блок к значениям из config/family.php. */
    public function resetContent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'in:leadership,positions,directions,promotion_criteria,about'],
        ]);

        FamilyContent::reset($data['key']);

        return back()->with('status', 'Повернуто до значень за замовчуванням.');
    }
}
