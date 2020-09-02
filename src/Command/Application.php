<?php
namespace RestOnPhp\Command;

use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication {
    public function registerCommands($commands) {
        foreach($commands as $command) {
            $this->add($command);
        }
    }
}