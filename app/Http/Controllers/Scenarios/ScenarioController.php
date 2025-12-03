<?php

namespace App\Http\Controllers\Scenarios;

use App\Http\Controllers\Controller;
use App\Http\Services\Scenarios\ScenarioService;

class ScenarioController extends Controller
{
    protected $scenarioService;

    public function __construct(ScenarioService $scenarioService)
    {
        $this->scenarioService = $scenarioService;
    }
}

