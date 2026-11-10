<?php

namespace ErnestDefoe\StickerBook\Console;

use ErnestDefoe\StickerBook\Starter;
use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;

/** php flarum sticker-book:starter [--hidden]: add the starter sets. */
class StarterCommand extends AbstractCommand
{
    public function __construct(private readonly Starter $starter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('sticker-book:starter')
            ->setDescription('Add Sticker Book\'s four starter sets (skips sets that already exist by name).')
            ->addOption('hidden', null, InputOption::VALUE_NONE, 'Add them hidden, to look over first.');
    }

    protected function fire(): int
    {
        $added = $this->starter->install((bool) $this->input->getOption('hidden'));
        $this->info("Added $added starter set(s).");

        return 0;
    }
}
