<?php

namespace Addons\AiAssistant\Support;

use App\Models\Setting;

/**
 * Довільний текст від адміна (Admin → Налаштування → Інструкції AI, поле
 * під конкретний процес) вставляється МІЖ основною задачею й фінальною
 * вимогою до формату відповіді — так кастомні правила (наприклад, де саме
 * шукати дату на скріні цієї гри) впливають на сам аналіз, а вимога
 * формату відповіді (JSON/одне речення/без лапок тощо) все одно лишається
 * останньою — модель найкраще тримається саме останньої інструкції.
 */
class AdminInstructions
{
    public static function insert(string $task, string $formatInstruction, string $settingKey): string
    {
        $extra = trim((string) (Setting::get($settingKey) ?? ''));

        $custom = $extra === '' ? '' : "\n\nДодаткові інструкції від адміністратора:\n{$extra}";

        return $task.$custom."\n\n".$formatInstruction;
    }
}
