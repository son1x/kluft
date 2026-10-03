<?php

namespace Tests\Feature\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Notifications\PrivilegedRoleGrantedNotification;
use App\Notifications\RolesGrantedNotification;
use App\Support\MailQueueEntry;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Mail bei neuem Konto bzw. neuen Rechten; Sicherheitshinweis an die
 * Technik-Admins bei Verwaltung/Admin/Super-Admin.
 */
class RoleNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function makeUser(string $email, array $assignments = [], string $status = 'active'): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => $status]);
        foreach ($assignments as [$role, $scopeType, $scopeKey]) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);
        }

        return $user->fresh();
    }

    #[TestDox('Neues Konto mit Rolle: Willkommensmail mit Erklärung der Rechte')]
    public function test_new_account_with_role_gets_welcome_mail(): void
    {
        Notification::fake();
        $this->actingAs($this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]));
        $undo = Repeater::fake();

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Neue Leitung', 'email' => 'neu@dpsg-trier.de', 'status' => 'active', 'roleAssignments' => [['assignment' => 'stufenadmin|stage|woelflinge']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $neu = User::query()->where('email', 'neu@dpsg-trier.de')->firstOrFail();
        Notification::assertSentTo($neu, RolesGrantedNotification::class, function (RolesGrantedNotification $n) use ($neu) {
            $mail = $n->toMail($neu);
            $text = implode("\n", $mail->introLines).implode("\n", $mail->outroLines);

            return $n->newAccount
                && $n->assignments === ['stufenadmin|stage|woelflinge']
                && str_contains($mail->subject, 'Zugang')
                && str_contains($text, 'Stufenadmin – Wölflinge (Stufenseite')
                && str_contains($text, 'buero@dpsg-trier.de hat dir einen Zugang')
                && str_ends_with((string) $mail->actionUrl, '/admin');
        });
        Notification::assertNotSentTo($neu, PrivilegedRoleGrantedNotification::class);
    }

    #[TestDox('Neues Konto ohne Rolle: keine Mail')]
    public function test_new_account_without_role_gets_no_mail(): void
    {
        Notification::fake();
        $this->actingAs($this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Ohne', 'email' => 'ohne@dpsg-trier.de', 'status' => 'active'])
            ->call('create')
            ->assertHasNoFormErrors();

        Notification::assertNothingSent();
    }

    #[TestDox('Neue Rolle „Verwaltung“: Mail an die Person und Sicherheitshinweis an die Technik-Admins')]
    public function test_privileged_role_notifies_person_and_admins(): void
    {
        Notification::fake();
        $admin = $this->makeUser('technik@dpsg-trier.de', [['admin', 'global', null]]);
        $actor = $this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]);
        $target = $this->makeUser('referentin@dpsg-trier.de', [['autor', 'own', null]]);
        $this->actingAs($actor);
        $undo = Repeater::fake();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roleAssignments' => [['assignment' => 'autor|own|'], ['assignment' => 'verwaltung|global|']]])
            ->call('save')
            ->assertHasNoFormErrors();
        $undo();

        Notification::assertSentTo($target, RolesGrantedNotification::class, fn (RolesGrantedNotification $n) => ! $n->newAccount && $n->assignments === ['verwaltung|global|']);
        Notification::assertSentTo($admin, PrivilegedRoleGrantedNotification::class, fn (PrivilegedRoleGrantedNotification $n) => $n->target->is($target));
        Notification::assertNotSentTo($actor, PrivilegedRoleGrantedNotification::class);
    }

    #[TestDox('Speichern ohne neue Rolle: keine Mail')]
    public function test_saving_without_new_roles_sends_nothing(): void
    {
        Notification::fake();
        $this->actingAs($this->makeUser('buero@dpsg-trier.de', [['verwaltung', 'global', null]]));
        $target = $this->makeUser('autor@dpsg-trier.de', [['autor', 'own', null]]);
        $undo = Repeater::fake();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['name' => 'Umbenannt', 'roleAssignments' => [['assignment' => 'autor|own|']]])
            ->call('save')
            ->assertHasNoFormErrors();
        $undo();

        Notification::assertNothingSent();
    }

    #[TestDox('Wartende Mail erscheint im Backend „E-Mail-Versand“ mit Art und Empfänger')]
    public function test_queued_notification_is_readable_in_mail_queue(): void
    {
        config(['queue.default' => 'database']);
        $user = $this->makeUser('neu@dpsg-trier.de');

        $user->notify(new RolesGrantedNotification(['autor|own|'], true, 'Büro'));

        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertSame(['label' => 'Zugang/neue Rechte', 'recipient' => 'neu@dpsg-trier.de'], MailQueueEntry::describe($payload));
    }
}
