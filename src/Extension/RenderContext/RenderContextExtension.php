<?php

namespace League\Plates\Extension\RenderContext;

use League\Plates;

/** The render context extension provides a RenderContext object and functions to be used within the render context object. This RenderContext object is injected into the template data to allow usefulness in the templates. */
final class RenderContextExtension implements Plates\Extension
{
    public function register(Plates\Engine $plates) {
        $c = $plates->getContainer();
        $c->addStack('renderContext.func', function($c) {
            return [
                'plates' => Plates\Util\stackGroup([
                    aliasNameFunc($c->get('renderContext.func.aliases')),
                    splitByNameFunc($c->get('renderContext.func.funcs'))
                ]),
                'notFound' => notFoundFunc(),
            ];
        });
        $c->add('renderContext.func.aliases', [
            'e' => 'escape',
            '__invoke' => 'escape',
            'stop' => 'end',
        ]);
        $c->add('renderContext.func.funcs', function($c) {
            $template_args = assertTemplateArgsFunc();
            $one_arg = assertArgsFunc(1);

            return [
                'insert' => [$template_args, insertFunc()],
                'escape' => [$one_arg, escapeFunc($c->get('escape'))],
                'data' => [assertArgsFunc(0, 1), templateDataFunc()],
                'name' => [accessTemplatePropFunc('name')],
                'context' => [accessTemplatePropFunc('context')],
                'component' => [$template_args, componentFunc()],
                'slot' => [$one_arg, slotFunc()],
                'end' => [endFunc()]
            ];
        });

        $c->wrapComposed('compose', function($composed, $c) {
            return array_merge($composed, [
                'renderContext.renderContext' => renderContextCompose(
                    $c->get('renderContext.factory'),
                    $c->get('config')['render_context_var_name']
                )
            ]);
        });
        $c->add('include.bind', function($c) {
            return renderContextBind($c->get('config')['render_context_var_name']);
        });
        $c->add('renderContext.factory', function($c) {
            return RenderContext::factory(
                function() use ($c) { return $c->get('renderTemplate'); },
                $c->get('renderContext.func')
            );
        });

        $plates->addMethods([
            'registerFunction' => function(Plates\Engine $e, $name, callable $func, callable $assert_args = null, $simple = true) {
                $c = $e->getContainer();
                $func = $simple ? wrapSimpleFunc($func) : $func;

                $c->wrap('renderContext.func.funcs', function($funcs, $c) use ($name, $func, $assert_args) {
                    $funcs[$name] = $assert_args ? [$assert_args, $func] : [$func];
                    return $funcs;
                });
            },
            'addFuncs' => function(Plates\Engine $e, callable $add_funcs, $simple = false) {
                $e->getContainer()->wrap('renderContext.func.funcs', function($funcs, $c) use ($add_funcs, $simple) {
                    $new_funcs = $simple
                        ? array_map(wrapSimpleFunc::class, $add_funcs($c))
                        : $add_funcs($c);
                    return array_merge($funcs, $new_funcs);
                });
            },
            'wrapFuncs' => function(Plates\Engine $e, callable $wrap_funcs) {
                $e->getContainer()->wrap('renderContext.func.funcs', $wrap_funcs);
            }
        ]);
    }
}
