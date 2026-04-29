<?php

namespace Tests\Unit\Rules;

use App\Models\Conge;
use App\Models\User;
use App\Rules\CheckLeaveBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests pour la règle de validation CheckLeaveBalance.
 */
class CheckLeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $employe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employe = User::factory()->create();
        $this->employe->assignRole('employe');

        // Créer des soldes de congés
        $this->employe->soldesConges()->create([
            'type' => Conge::TYPE_ANNUEL,
            'solde' => 25,
            'annee' => now()->year,
        ]);
    }

    /** @test */
    public function la_regle_passe_si_solde_suffisant(): void
    {
        $rule = new CheckLeaveBalance($this->employe->id, Conge::TYPE_ANNUEL);

        $validator = validator(
            ['date_debut' => now()->addDay(), 'date_fin' => now()->addDays(5)],
            ['date_debut' => 'required', 'date_fin' => 'required']
        );

        $passes = $rule->validate('date_fin', now()->addDays(5), fn () => null);

        $this->assertNull($passes);
    }

    /** @test */
    public function la_regle_echoue_si_solde_insuffisant(): void
    {
        // Créer des congés qui épuisent le solde
        Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'type' => Conge::TYPE_ANNUEL,
            'etat' => Conge::ETAT_APPROUVE,
            'nombre_jours' => 25,
        ]);

        $rule = new CheckLeaveBalance($this->employe->id, Conge::TYPE_ANNUEL);

        $failCalled = false;
        $failMessage = null;

        $rule->validate('date_fin', now()->addDays(10), function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        });

        $this->assertTrue($failCalled);
        $this->assertStringContainsString('solde', $failMessage);
    }

    /** @test */
    public function les_conges_maladie_ne_consomment_pas_de_solde(): void
    {
        // Pas de solde défini pour maladie
        $rule = new CheckLeaveBalance($this->employe->id, Conge::TYPE_MALADIE);

        $passes = true;
        $rule->validate('date_fin', now()->addDays(30), function () use (&$passes) {
            $passes = false;
        });

        $this->assertTrue($passes);
    }

    /** @test */
    public function la_regle_ignore_le_conge_en_cours_de_modification(): void
    {
        // Créer un congé existant
        $congeExistant = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'type' => Conge::TYPE_ANNUEL,
            'etat' => Conge::ETAT_BROUILLON,
            'nombre_jours' => 10,
        ]);

        $rule = new CheckLeaveBalance(
            $this->employe->id,
            Conge::TYPE_ANNUEL,
            $congeExistant->id
        );

        $passes = true;
        $rule->validate('date_fin', now()->addDays(10), function () use (&$passes) {
            $passes = false;
        });

        $this->assertTrue($passes);
    }
}
