<?php

namespace Addons\Progression\Events;

/**
 * Диспатчиться з ProgressionService::unlock() одразу після того, як
 * учаснику вперше зараховано ачівку. Notifications (якщо встановлено)
 * слухає цю подію рядковим літералом класу — так само, як Progression сам
 * слухає report.reviewed від Reports, — щоб не тягнути жорсткої залежності
 * в жоден бік.
 */
class AchievementUnlocked
{
    public function __construct(
        public readonly int $userId,
        public readonly string $achievementCode,
        public readonly string $achievementName,
    ) {
    }
}
