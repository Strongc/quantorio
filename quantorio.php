<?php

require 'Generator.php';

$target = 'public';

if (class_exists('lua')) {

    $lua = new lua;
    $lua->registerCallback('putdata', function ($data) use ($target) {
        (new FactorioGenerator($target))->parseMods('data', $data)->save();

    });
    $modPath;
    $currentPath;
    $lua->registerCallback('php_findfile', function ($path) use (&$modPath, &$currentPath, $lua) {
        $path = str_replace('.', '/', $path);
        if (is_file("core/lualib/{$path}.lua")) {
            $filename = "core/lualib/$path.lua";
        } elseif (is_file("{$modPath}/{$path}.lua")) {
            $filename = "{$modPath}/{$path}.lua";
        } elseif(is_file("{$currentPath}/{$path}.lua")) {
            $filename = "{$currentPath}/{$path}.lua";
        } else {
            var_dump($modPath, $path);
        }
        $currentPath = dirname($filename);

        return $lua->include($filename);
    });
    $lua->registerCallback('module', function () {

    });
    $lua->include('core/prefix.lua');
    $lua->include("core/lualib/defines.lua");
    $lua->include("core/lualib/util.lua");

    foreach ([
        'inventory',
        'transport_line',
        'direction',
        'riding',
        'command',
        'distraction',
        'compoundcommandtype',
        'difficulty',
        'events',
        'controllers',
        'groupstate',
        'circuitconnector',
        'circuitconnectorid',
        'circuitconditionindex',
        'trainstate',
        'signal_state',
        'chain_signal_state',
        'rail_direction',
        'rail_connection_direction',
    ] as $block) {
        $lua->eval("
                defines.$block = $block

            ");
    }

    foreach ([
        'distance',
        'findfirstentity',
        'positiontostr',
        'table.deepcopy',
        'table.compare',
        'formattime',
        'moveposition',
        'oppositedirection',
        'ismoduleavailable',
        'multiplystripes',
    ] as $block) {
        $lua->eval("util.$block = $block");
    }



    foreach (['builder', 'dataloader', 'story', 'util'] as $luafile) {
        $lua->include("core/lualib/{$luafile}.lua");

    }
    $lua->eval("data.raw['gui-style'] = {}");
    $lua->eval("data.raw['gui-style'].default = {}");

    $mods = [];

    foreach (glob('data/*') as $path) {
        if (!is_file("{$path}/info.json")) {
            continue;
        }
        $info = json_decode(file_get_contents("{$path}/info.json"));

        $mods[$info->name][$info->version] = ["path" => $path, "dependencies" => isset($info->dependencies) ? $info->dependencies : []];
    }
    $modsToBeLoad = [];
    for($i = 0; $i < 5; $i++) {
        foreach ($mods as $name => $mod) {
            krsort($mod);
            if (!isset($modsToBeLoad[$name])) {
                $modsToBeLoad[$name] = 0;
            }
            foreach (reset($mod)['dependencies'] as $dependency) {
                $splits = preg_split('~\s~', $dependency);
                $depName;
                switch($count = count($splits)) {
                    case 4:
                    case 3:
                        $depName = $splits[$count - 3];break;
                    case 2:
                    case 1:
                        $depName = $splits[$count - 1];break;
                }


                if (isset($modsToBeLoad[$depName])) {
                    $modsToBeLoad[$depName] = max($modsToBeLoad[$depName], $modsToBeLoad[$name] + 1);
                } else {
                    $modsToBeLoad[$depName] = $modsToBeLoad[$name] + 1;
                }
            }
        }
    }
    arsort($modsToBeLoad);
    foreach(['data','data-updates','data-final-fixes'] as $file) {
        foreach ($modsToBeLoad as $name => $level) {
            if(!isset($mods[$name])) {
                continue;
            }
            $modPath = reset($mods[$name])['path'];
            if(is_file("{$modPath}/{$file}.lua")) {
                try {
                    $lua->include("{$modPath}/{$file}.lua");
                } catch(LuaException $e) {
                    var_dump($e->getMessage());
                }
            }
        }
    }

    $lua->eval('putdata(data.raw)');
} else {

    (new FactorioGenerator($target))->parseMods('data')->save();
}
