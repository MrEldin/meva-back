<?php

namespace App\Console\Meva\Commands;

use App\Console\Meva\Generators\GenerateMevaController;
use App\Console\Meva\Generators\GenerateMevaModel;
use App\Console\Meva\Generators\GenerateMevaService;
use App\Console\Meva\Generators\GenerateMevaTestFile;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class MakeMevaController extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:l-controller {name} {--fillable=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a controller';

    protected $type = 'Controller';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the command.
     *
     * @return void
     * @see fire()
     */
    public function handle()
    {
        $this->laravel->call([$this, 'fire'], func_get_args());
    }

    public function fire()
    {

        try {

            (new GenerateMevaModel([
                'name'     => $this->argument('name'),
                'fillable' => $this->option('fillable')
            ]))->run();

            $model = (new GenerateMevaModel(['name' => $this->argument('name')]))->getMainNamespace();

            $this->call('make:l-request', [
                'name'     => $this->argument('name'),
                'sub_name' => 'Create',
            ]);

            $this->call('make:l-request', [
                'name'     => $this->argument('name'),
                'sub_name' => 'Update',
            ]);

            $this->call('make:l-request', [
                'name'     => $this->argument('name'),
                'sub_name' => 'Get',
            ]);

            $this->call('make:l-request', [
                'name'     => $this->argument('name'),
                'sub_name' => 'Delete',
            ]);

            list($createService,
                $updateService,
                $getService,
                $indexService,
                $deleteService) = $this->generateServices($model);

            $createService->run();
            $updateService->run();
            $deleteService->run();
            $getService->run();
            $indexService->run();

            (new GenerateMevaController([
                'name'           => $this->argument('name'),
                'create_service' => $createService->getMainNamespace('Create'),
                'update_service' => $updateService->getMainNamespace('Update'),
                'get_service'    => $getService->getMainNamespace('Get'),
                'index_service'  => $indexService->getMainNamespace('Index'),
                'delete_service' => $deleteService->getMainNamespace('Delete'),
            ]))->run();

            $this->call('make:l-routes', [
                'name' => $this->argument('name')
            ]);

            $this->generateTests($model);

            $this->info("Meva controller successfully created.");
            $this->info("Please register the new service provider in bootstrap/providers.php.");

        } catch (\Exception $e) {
            $this->error($this->type . ' already exists!');
            $this->error($e->getMessage());

            return false;
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the entity.'],
        ];
    }

    /**
     * @param string $model
     * @throws \App\Console\Meva\Exceptions\FileAlreadyExistsException
     */
    private function generateTests(string $model): void
    {
        (new GenerateMevaTestFile([
            'name'     => $this->argument('name'),
            'sub_name' => 'Create',
            'model'    => $model,
            'method'   => 'post',
            'url'      => '/api/' . strtolower($this->argument('name')),
        ]))->run();

        (new GenerateMevaTestFile([
            'name'     => $this->argument('name'),
            'sub_name' => 'Update',
            'model'    => $model,
            'method'   => 'put',
            'url'      => '/api/' . strtolower($this->argument('name')) . '/1',
        ]))->run();


        (new GenerateMevaTestFile([
            'name'     => $this->argument('name'),
            'sub_name' => 'CreateX',
            'model'    => $model,
            'method'   => 'post',
            'url'      => '/api/' . strtolower($this->argument('name')),
        ]))->run();

        (new GenerateMevaTestFile([
            'name'     => $this->argument('name'),
            'sub_name' => 'UpdateX',
            'model'    => $model,
            'method'   => 'put',
            'url'      => '/api/' . strtolower($this->argument('name')) . '/1',
        ]))->run();

        (new GenerateMevaTestFile([
            'name'     => $this->argument('name'),
            'sub_name' => 'Get',
            'model'    => $model,
            'method'   => 'get',
            'url'      => '/api/' . strtolower($this->argument('name')),
        ]))->run();

        (new GenerateMevaTestFile([
            'name'     => $this->argument('name'),
            'sub_name' => 'Delete',
            'model'    => $model,
            'method'   => 'delete',
            'url'      => '/api/' . strtolower($this->argument('name')) . '/1',
        ]))->run();
    }

    /**
     * @param string $model
     * @return array
     */
    private function generateServices(string $model): array
    {
        $createService = (new GenerateMevaService([
            'name'     => $this->argument('name'),
            'sub_name' => 'Create',
            'model'    => $model,
            'method'   => 'create',
            'fillable' => $this->option('fillable')
        ]));

        $updateService = (new GenerateMevaService([
            'name'     => $this->argument('name'),
            'sub_name' => 'Update',
            'model'    => $model,
            'method'   => 'update',
            'sub_data' => '$id, ',
            'fillable' => $this->option('fillable')
        ]));

        $deleteService = (new GenerateMevaService([
            'name'     => $this->argument('name'),
            'sub_name' => 'Delete',
            'model'    => $model,
            'method'   => 'delete',
            'sub_data' => '$id',
            'fillable' => $this->option('fillable')
        ]));

        $getService = (new GenerateMevaService([
            'name'     => $this->argument('name'),
            'sub_name' => 'Get',
            'model'    => $model,
            'sub_data' => '$id',
            'method'   => 'find',
            'fillable' => $this->option('fillable')
        ]));

        $indexService = (new GenerateMevaService([
            'name'     => $this->argument('name'),
            'sub_name' => 'Index',
            'model'    => $model,
            'method'   => 'get',
            'fillable' => $this->option('fillable')
        ]));

        return array($createService, $updateService, $getService, $indexService, $deleteService);
    }
}
