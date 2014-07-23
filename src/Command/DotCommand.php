<?php

namespace UmlGeneratorPhp\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use UmlGeneratorPhp;
use UmlGeneratorPhp\DrupalDocumentation;
use UmlGeneratorPhp\OopToDot;

class DotCommand extends Command
{
    protected function configure()
    {
        $this
          ->setName('generate:dot')
          ->setDescription('Generate dot files from the json structure')
          ->addArgument(
            'directory',
            InputArgument::REQUIRED,
            'The directory containing the JSON files.'
          )
          ->addOption(
            'parents',
            'p',
            InputOption::VALUE_NONE,
            'Add parents into file.'
          )
          ->addOption(
            'documenter',
            'd',
            InputOption::VALUE_REQUIRED,
            'Set documentation url generator.'
          )
          ->addOption(
            'parent-limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Limits the max depth of parents in a single graphviz file (all by default)',
            PHP_INT_MAX
          );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = realpath($input->getArgument('directory'));
        if ($directory === false) {
            $output->writeln('<error>Directory not found</error>');
            exit(1);
        }

        if (!is_file($directory . '/uml-generator-php.index')) {
            $output->writeln("<error>No index file found. You need to run `uml-generator-php generate:json` first.</error>");
            exit(1);
        };

        $with_parents = $input->getOption('parents');

        switch (strtolower($input->getOption('documenter'))) {
            case "drupal":
                $meta = array(
                  'siteURL' => 'https://api.drupal.org/api',
                  'basePath' => '/srv/http/dp8.dev/',
                  'component' => 'drupal',
                  'version' => '8',
                );
                $documenter = new DrupalDocumentation($meta);
                break;
            default:
                $documenter = null;
                break;
        }


        $toDot = new OopToDot($documenter);

        $finder = new Finder();
        $finder->files()->ignoreUnreadableDirs()->in($directory);
        $files = $finder->name('*.json');

        $limit = $input->getOption('parent-limit');

        foreach ($files as $file) {
            $array = json_decode(file_get_contents($file), TRUE);
            if ($array !== null || !$this->checkValidJson($array)) {
                if ($with_parents) {
                    $file_index = json_decode(file_get_contents($directory . '/uml-generator-php.index'), true);
                    $dot = $toDot->getMergedDiagram($array, $file_index, $limit);
                } else {
                    $dot = $toDot->getClassDiagram($array);
                }

                $pinfo = pathinfo($file);
                $outputfile = $pinfo['dirname'] . '/' . $pinfo['filename'] . '.dot';
                //$output->writeln($outputfile);
                file_put_contents($outputfile, $dot);
            }
        }
    }


    /**
     * This method checks if the json structure looks at least something like a parseable
     * php definition from uml-generator-php generate:json
     *
     * @param array $json
     * @return bool
     */
    private function checkValidJson(array $json)
    {
        if (!isset($json[0])) return false;
        if (!isset($json[0]['type'])) return false;
        if (!isset($json[0]['name'])) return false;
        return true;
    }

} 