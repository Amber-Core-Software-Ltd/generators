<?php

namespace AmberCore\Generator\Command;

use App\Helper\FileWorkTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

class TypesCommands extends Command
{
    use FileWorkTrait;

    protected static $defaultName = 'amber-core:generator:graphql:types';

    /**
     * AmberCoreGeneratorGraphqlTypesCommand constructor.
     *
     * @param string|null        $name
     * @param ContainerInterface $container
     */
    public function __construct(string $name = null, private ContainerInterface $container)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('A command to generate GraphQL Types for overblog/graphql-bundle from Symfony Entities')
            ->addArgument('types-path', InputArgument::OPTIONAL, 'Path to types', 'config/graphql/types/entity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var EntityManagerInterface $em */
        $em       = $this->container->get('doctrine')->getManager();
        $rootPath = $this->container->get('kernel')->getProjectDir();

        $entities          = $em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $entity_namespaces = $em->getConfiguration()->getEntityNamespaces();

        $types = [];
        $io->note(message: 'Found ' . count($entities) . ' Entities');

        $all_entities = false;
        foreach ($entities as $entity)
        {
            if (!$all_entities)
            {
                $answer = $io->ask('Generate type for ' . $entity . ' [y/n/all]?', default: 'y');
                if ($answer === 'all')
                {
                    $all_entities = true;
                }
                if ($answer === 'n')
                {
                    continue;
                }
            }

            $meta_data = $em->getClassMetadata($entity);
            $fields    = $meta_data->fieldMappings;
            $relations = $meta_data->associationMappings;

            $type_fields = [];
            // Create simple fields types
            foreach ($fields as $name => $field)
            {
                $type_fields[$name] = [
                    'type' => $this->castType($field['type'], $field['nullable']),
                ];
            }

            // Create relations types
            foreach ($relations as $name => $relation)
            {
                $type_fields[$name] = [
                    'type' => $this->castRelation(
                        $this->removeEntityNamespaces($entity_namespaces, $relation['targetEntity']),
                        $relation['type']
                    ),
                ];
            }

            $types[$this->removeEntityNamespaces($entity_namespaces, $meta_data->name)] = [
                'type' => 'object',
                'config' => [
                    'fields' => $type_fields,
                ],
            ];
        }

        $overwrite_all_files = false;
        foreach ($types as $name => $type)
        {
            $yaml = Yaml::dump([
                $this->classNameToTypeName($name) => $type,
            ], inline: 30, indent: 2);

            $file_path = $rootPath . '/' . $input->getArgument('types-path') . '/' . str_replace(search: '\\', replace:
                    '/', subject: $name) . '.yaml';

            $this->checkDir($file_path);
            $io->info(message: 'Saving ' . $this->classNameToTypeName($name) . ' to ' . $file_path);

            if(!$overwrite_all_files && file_exists($file_path))
            {
                $answer = $io->ask($file_path . ' already exist. Overwrite it [y/n/all]?', 'y');
                if($answer === 'all')
                {
                    $overwrite_all_files = true;
                }
                if($answer === 'n')
                {
                    continue;
                }
            }
            if (file_put_contents($file_path, $yaml) === false)
            {
                $io->error('Failed to save ' . $this->classNameToTypeName($name) . ' in ' . $file_path);
            }
            else
            {
                $io->success($this->classNameToTypeName($name) . ' was saved in ' . $file_path);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $type
     * @param bool   $nullable
     *
     * @return string|null
     */
    private function castType(string $type, bool $nullable): ?string
    {
        $simple_map = [
            'boolean' => 'Boolean',
            'integer' => 'Int',
            'smallint' => 'Int',

            'decimal' => 'Float',
            'float' => 'Float',

            'string' => 'String',
            'text' => 'String',
            'json' => 'String',
            'date' => 'String',
        ];

        if (array_key_exists($type, $simple_map))
        {
            return $simple_map[$type] . ($nullable ? '' : '!');
        }

        return null;
    }

    /**
     * @param string $targetEntity
     * @param int    $type
     *
     * @return string
     */
    private function castRelation(string $targetEntity, int $type): string
    {
        return $this->classNameToTypeName(match ($type)
        {
            1, 2 => $targetEntity,
            3, 4 => '[' . $targetEntity . ']'
        });
    }

    /**
     * @param int|string $name
     *
     * @return string
     */
    private function classNameToTypeName(int|string $name): string
    {
        return str_replace(search: '\\', replace: '_', subject: $name);
    }

    /**
     * @param array  $namespaces
     * @param string $name
     *
     * @return string
     */
    private function removeEntityNamespaces(array $namespaces, string $name): string
    {
        foreach ($namespaces as $namespace)
        {
            if (str_starts_with(haystack: $name, needle: $namespace))
            {
                return str_replace(search: $namespace . '\\', replace: '', subject: $name);
            }
        }

        return $name;
    }
}
