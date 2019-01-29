<?php

namespace STS\VisualTesting\Console;

use Laravel\Dusk\Console\DuskCommand as BaseDuskCommand;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;

class DuskCommand extends BaseDuskCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dusk 
                            {--without-tty : Disable output to TTY}
                            {--without-percy : Disable percy snapshots}
                            {--percy-target-branch : Set the base branch for comparison}
                            {--percy-target-commit : Set the base commit SHA for comparison}';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->purgeScreenshots();

        $this->purgeConsoleLogs();

        return $this->withDuskEnvironment(function () {
            $process = $this->process();

            try {
                $process->setTty(!$this->option('without-tty'));
            } catch (RuntimeException $e) {
                $this->output->writeln('Warning: ' . $e->getMessage());
            }

            return $process->run(function ($type, $line) {
                $this->output->write($line);
            }, $this->env());
        });
    }

    /**
     * Prepend the Percy token and wrapper command
     *
     * @return array
     */
    protected function binary()
    {
        if ($this->option('without-percy')) {
            return parent::binary();
        }

        return array_merge([
            'npx',
            'percy',
            'exec',
            '--'
        ], parent::binary());
    }

    /**
     * @return array
     */
    protected function env()
    {
        return array_filter([
            'PERCY_TOKEN'         => env('PERCY_TOKEN'),
            'PERCY_TARGET_BRANCH' => $this->baseBranch(),
            'PERCY_TARGET_COMMIT' => $this->targetCommit()
        ]);
    }

    /**
     * @return string|null
     */
    protected function baseBranch()
    {
        return $this->hasOption('percy-target-branch')
            ? $this->option('percy-target-branch')
            : env('PERCY_TARGET_BRANCH');
    }

    protected function targetCommit()
    {
        return $this->hasOption('percy-target-commit')
            ? $this->option('percy-target-commit')
            : env('PERCY_TARGET_COMMIT');
    }

    /**
     * @return Process
     */
    protected function process()
    {
        return (new Process(array_merge(
            $this->binary(), $this->phpunitArguments($this->processOptions())
        )))->setTimeout(null);
    }

    /**
     * @return array
     */
    protected function processOptions()
    {
        return array_diff(
            array_slice($_SERVER['argv'], 2),
            ['--without-tty', '--without-percy', '--percy-target-branch', '--percy-target-commit']
        );
    }
}