<?php

namespace BRISP\LivewireComponentRequests\Tests\Unit;

use BRISP\LivewireComponentRequests\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class GeneratorCommandCreatesNewLivewireComponentRequestTest extends TestCase
{
    public function test_command_creates_a_new_event_class()
    {
        $class = app_path('Http/Requests/Livewire/TestRequest.php');

        if (File::exists($class)) {
            unlink($class);
        }

        $this->assertFalse(File::exists($class));

        Artisan::call('make:component-request TestRequest');

        $this->assertTrue(File::exists($class));
    }
}
