<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\StockNotifications\Pages\ListStockNotifications;
use App\Filament\Resources\SyncRuns\Pages\ListSyncRuns;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_published_content_and_view_operations(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        Livewire::test(CreatePage::class)->fillForm([
            'title' => 'About', 'slug' => 'about', 'is_published' => true,
            'blocks' => [['type' => 'text', 'data' => ['text' => 'Welcome']]],
        ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('pages', ['slug' => 'about', 'is_published' => true]);
        foreach ([ManageOrders::class, ManageUsers::class, ListStockNotifications::class, ListSyncRuns::class] as $page) {
            Livewire::test($page)->assertOk();
        }
    }

    public function test_editing_page_preserves_existing_row_layout(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $blocks = [['columns' => [['blocks' => [['type' => 'text', 'text' => 'Legacy']]]]]];
        $page = \App\Models\Page::query()->create(['title' => 'Legacy', 'slug' => 'legacy', 'blocks' => $blocks]);
        Livewire::test(\App\Filament\Resources\Pages\Pages\EditPage::class, ['record' => $page->slug])
            ->fillForm(['title' => 'Updated'])->call('save')->assertHasNoFormErrors();
        $this->assertEquals($blocks, $page->fresh()->blocks);
    }
}
