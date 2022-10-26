<?php

namespace BRISP\LivewireComponentRequests\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeLivewireComponentRequestCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'make:component-request';

    /**
     * @var string
     */
    protected $description = 'Create a new livewire component request.';

    /**
     * @var string
     */
    protected $type = 'ComponentRequest';

    /**
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../stubs/component-request.php.stub';
    }

    /**
     * @param $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Http\Requests\Livewire';
    }

    /**
     * @return bool|void|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        parent::handle();

        $this->doOtherOperations();
    }

    /**
     * @return void
     */
    protected function doOtherOperations()
    {
        $class = $this->qualifyClass($this->getNameInput());

        $path = $this->getPath($class);

        $content = file_get_contents($path);

        file_put_contents($path, $content);
    }
}
