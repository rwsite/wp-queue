<?php

declare(strict_types=1);

// WordPress functions mocks for AdminPage tests
if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (! function_exists('add_menu_page')) {
    function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, string $icon_url = '', ?int $position = null): string
    {
        return $menu_slug;
    }
}

use WPQueue\Admin\AdminPage;

// Эти тесты проверяют структуру AdminPage без WordPress окружения

test('AdminPage регистрирует меню верхнего уровня', function (): void {
    $adminPage = new AdminPage();

    // Проверяем что метод addMenuPage существует
    expect(method_exists($adminPage, 'addMenuPage'))->toBeTrue();
});

test('AdminPage имеет 4 основные вкладки', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    // Получаем приватное свойство tabs
    $tabsProperty = $reflection->getProperty('tabs');
    $tabsProperty->setAccessible(true);
    $tabs = $tabsProperty->getValue($adminPage);

    expect($tabs)->toBeArray();
    expect($tabs)->toHaveCount(4);
    expect($tabs)->toHaveKey('queues');
    expect($tabs)->toHaveKey('scheduler');
    expect($tabs)->toHaveKey('diagnostics');
    expect($tabs)->toHaveKey('docs');
});

test('AdminPage имеет секции для каждой вкладки', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $sectionsProperty = $reflection->getProperty('sections');
    $sectionsProperty->setAccessible(true);
    $sections = $sectionsProperty->getValue($adminPage);

    expect($sections)->toBeArray();
    expect($sections)->toHaveKey('queues');
    expect($sections)->toHaveKey('scheduler');
    expect($sections)->toHaveKey('diagnostics');
    expect($sections)->toHaveKey('docs');
});

test('Вкладка Очереди имеет 5 секций', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $sectionsProperty = $reflection->getProperty('sections');
    $sectionsProperty->setAccessible(true);
    $sections = $sectionsProperty->getValue($adminPage);

    expect($sections['queues'])->toHaveCount(5);
    expect($sections['queues'])->toHaveKey('overview');
    expect($sections['queues'])->toHaveKey('jobs');
    expect($sections['queues'])->toHaveKey('history');
    expect($sections['queues'])->toHaveKey('drivers');
    expect($sections['queues'])->toHaveKey('settings');
});

test('Вкладка Планировщик заданий имеет 5 секций', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $sectionsProperty = $reflection->getProperty('sections');
    $sectionsProperty->setAccessible(true);
    $sections = $sectionsProperty->getValue($adminPage);

    expect($sections['scheduler'])->toHaveCount(5);
    expect($sections['scheduler'])->toHaveKey('overview');
    expect($sections['scheduler'])->toHaveKey('events');
    expect($sections['scheduler'])->toHaveKey('paused');
    expect($sections['scheduler'])->toHaveKey('schedules');
    expect($sections['scheduler'])->toHaveKey('settings');
});

test('Вкладка Диагностика имеет 4 секции', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $sectionsProperty = $reflection->getProperty('sections');
    $sectionsProperty->setAccessible(true);
    $sections = $sectionsProperty->getValue($adminPage);

    expect($sections['diagnostics'])->toHaveCount(4);
    expect($sections['diagnostics'])->toHaveKey('health');
    expect($sections['diagnostics'])->toHaveKey('environment');
    expect($sections['diagnostics'])->toHaveKey('logs');
    expect($sections['diagnostics'])->toHaveKey('tools');
});

test('Вкладка Документация имеет 5 секций', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $sectionsProperty = $reflection->getProperty('sections');
    $sectionsProperty->setAccessible(true);
    $sections = $sectionsProperty->getValue($adminPage);

    expect($sections['docs'])->toHaveCount(5);
    expect($sections['docs'])->toHaveKey('intro');
    expect($sections['docs'])->toHaveKey('quickstart');
    expect($sections['docs'])->toHaveKey('api');
    expect($sections['docs'])->toHaveKey('cli');
    expect($sections['docs'])->toHaveKey('faq');
});

test('AdminPage имеет метод renderPage', function (): void {
    $adminPage = new AdminPage();
    expect(method_exists($adminPage, 'renderPage'))->toBeTrue();
});

test('AdminPage имеет метод enqueueAssets', function (): void {
    $adminPage = new AdminPage();
    expect(method_exists($adminPage, 'enqueueAssets'))->toBeTrue();
});

test('Каждая вкладка имеет title и icon', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $tabsProperty = $reflection->getProperty('tabs');
    $tabsProperty->setAccessible(true);
    $tabs = $tabsProperty->getValue($adminPage);

    foreach ($tabs as $tabKey => $tabData) {
        expect($tabData)->toHaveKey('title');
        expect($tabData)->toHaveKey('icon');
        expect($tabData['title'])->not->toBeEmpty();
        expect($tabData['icon'])->toStartWith('dashicons-');
    }
});

test('Каждая секция имеет title и icon', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $sectionsProperty = $reflection->getProperty('sections');
    $sectionsProperty->setAccessible(true);
    $sections = $sectionsProperty->getValue($adminPage);

    foreach ($sections as $tabKey => $tabSections) {
        foreach ($tabSections as $sectionKey => $sectionData) {
            expect($sectionData)->toHaveKey('title');
            expect($sectionData)->toHaveKey('icon');
            expect($sectionData['title'])->not->toBeEmpty();
        }
    }
});

test('AdminPage рендерит методы для секций Очереди', function (): void {
    $adminPage = new AdminPage();

    expect(method_exists($adminPage, 'renderQueuesOverview'))->toBeTrue();
    expect(method_exists($adminPage, 'renderQueuesJobs'))->toBeTrue();
    expect(method_exists($adminPage, 'renderQueuesHistory'))->toBeTrue();
});

test('AdminPage рендерит методы для секций Планировщика', function (): void {
    $adminPage = new AdminPage();

    expect(method_exists($adminPage, 'renderSchedulerEvents'))->toBeTrue();
});

test('AdminPage рендерит методы для секций Диагностики', function (): void {
    $adminPage = new AdminPage();

    expect(method_exists($adminPage, 'renderDiagnosticsEnvironment'))->toBeTrue();
});

test('AdminPage рендерит методы для секций Документации', function (): void {
    $adminPage = new AdminPage();

    expect(method_exists($adminPage, 'renderDocsIntro'))->toBeTrue();
});

test('Метод getStatusLabel возвращает корректные метки', function (): void {
    $adminPage = new AdminPage();
    $reflection = new ReflectionClass($adminPage);

    $method = $reflection->getMethod('getStatusLabel');
    $method->setAccessible(true);

    expect($method->invoke($adminPage, 'idle'))->not->toBeEmpty();
    expect($method->invoke($adminPage, 'pending'))->not->toBeEmpty();
    expect($method->invoke($adminPage, 'running'))->not->toBeEmpty();
    expect($method->invoke($adminPage, 'paused'))->not->toBeEmpty();
    expect($method->invoke($adminPage, 'completed'))->not->toBeEmpty();
    expect($method->invoke($adminPage, 'failed'))->not->toBeEmpty();
});
