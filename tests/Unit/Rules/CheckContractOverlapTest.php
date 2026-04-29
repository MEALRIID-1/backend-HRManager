<?php

namespace Tests\Unit\Rules;

use App\Models\Contrat;
use App\Models\User;
use App\Rules\CheckNoContractOverlap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests pour la règle de validation CheckNoContractOverlap.
 */
class CheckContractOverlapTest extends TestCase
{
    use RefreshDatabase;

    private User $employe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employe = User::factory()->create();
    }

    /** @test */
    public function la_regle_passe_sans_cheveauchement(): void
    {
        // Créer un contrat existant
        Contrat::factory()->create([
            'employe_id' => $this->employe->id,
            'date_debut' => now()->subMonths(6),
            'date_fin' => now()->subMonth(),
            'etat' => Contrat::ETAT_TERMINE,
        ]);

        $rule = new CheckNoContractOverlap($this->employe->id);

        $passes = true;
        $rule->validate('date_fin', now()->addMonths(6), function () use (&$passes) {
            $passes = false;
        });

        $this->assertTrue($passes);
    }

    /** @test */
    public function la_regle_echoue_avec_cheveauchement(): void
    {
        // Créer un contrat actif
        Contrat::factory()->create([
            'employe_id' => $this->employe->id,
            'date_debut' => now()->subMonth(),
            'date_fin' => now()->addMonths(5),
            'etat' => Contrat::ETAT_ACTIF,
        ]);

        $rule = new CheckNoContractOverlap($this->employe->id);

        $failCalled = false;
        $failMessage = null;

        $rule->validate('date_debut', now(), function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        });

        $this->assertTrue($failCalled);
        $this->assertStringContainsString('contrat', $failMessage);
    }

    /** @test */
    public function un_contrat_cdi_peut_avoir_un_avenant_sans_cheveauchement(): void
    {
        // CDI existant
        Contrat::factory()->create([
            'employe_id' => $this->employe->id,
            'type' => Contrat::TYPE_CDI,
            'date_debut' => now()->subYear(),
            'date_fin' => null,
            'etat' => Contrat::ETAT_ACTIF,
        ]);

        $rule = new CheckNoContractOverlap($this->employe->id);

        $passes = true;
        $rule->validate('date_debut', now()->addYear(), function () use (&$passes) {
            $passes = false;
        });

        $this->assertFalse($passes);
    }

    /** @test */
    public function la_regle_ignore_le_contrat_en_cours_de_modification(): void
    {
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employe->id,
            'date_debut' => now(),
            'date_fin' => now()->addMonths(6),
            'etat' => Contrat::ETAT_ACTIF,
        ]);

        $rule = new CheckNoContractOverlap($this->employe->id, $contrat->id);

        $passes = true;
        $rule->validate('date_fin', now()->addMonths(8), function () use (&$passes) {
            $passes = false;
        });

        $this->assertTrue($passes);
    }
}
