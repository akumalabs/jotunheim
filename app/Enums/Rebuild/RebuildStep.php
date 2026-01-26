<?php

namespace App\Enums\Rebuild;

enum RebuildStep: string
{
    case STOPPING_SERVER = 'stopping_server';
    case DELETING_SERVER = 'deleting_server';
    case INSTALLING_OS = 'installing_os';
    case CONFIGURING_RESOURCES = 'configuring_resources';
    case BOOTING_SERVER = 'booting_server';
    case FINALIZING = 'finalizing';

    public function pveTaskType(): string
    {
        return match($this) {
            self::STOPPING_SERVER => 'qmstop',
            self::DELETING_SERVER => 'qmdestroy',
            self::INSTALLING_OS => 'qmclone',
            self::CONFIGURING_RESOURCES => 'qmconfig',
            self::BOOTING_SERVER => 'qmstart',
            self::FINALIZING => 'agent-ping',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::STOPPING_SERVER => 'Stopping server',
            self::DELETING_SERVER => 'Delete server',
            self::INSTALLING_OS => 'Installing OS',
            self::CONFIGURING_RESOURCES => 'Configuring resources',
            self::BOOTING_SERVER => 'Booting server',
            self::FINALIZING => 'Finalize',
        };
    }

    public function progressPercentage(): int
    {
        return match($this) {
            self::STOPPING_SERVER => 0,
            self::DELETING_SERVER => 10,
            self::CONFIGURING_RESOURCES => 75,
            self::BOOTING_SERVER => 90,
            self::FINALIZING => 100,
            self::INSTALLING_OS => 20, // Base for cloning, will add sub-progress
        };
    }

    public function hasProgress(): bool
    {
        return $this === self::INSTALLING_OS;
    }

    public function subOperations(): array
    {
        return [];
    }

}
