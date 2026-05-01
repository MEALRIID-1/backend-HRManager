@extends('emails.layout')

@section('title', 'Votre contrat de travail')

@section('content')
    <h2>Votre contrat de travail {{ $contrat->type }}</h2>
    
    <p>Bonjour {{ $employe->prenom }},</p>
    
    <p>Nous avons le plaisir de vous informer que votre contrat de travail a été créé et enregistré dans le système HRManager.</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <span class="badge badge-success" style="font-size: 16px; padding: 10px 20px;">✓ CONTRAT ACTIF</span>
    </div>
    
    <div class="info-box">
        <h3>📋 Informations du contrat</h3>
        <table class="data-table">
            <tr>
                <th>Type de contrat</th>
                <td><strong>{{ $contrat->type }}</strong></td>
            </tr>
            <tr>
                <th>Date de début</th>
                <td>{{ $contrat->date_debut->format('d/m/Y') }}</td>
            </tr>
            @if($contrat->date_fin)
            <tr>
                <th>Date de fin</th>
                <td>{{ $contrat->date_fin->format('d/m/Y') }}</td>
            </tr>
            @endif
            <tr>
                <th>Salaire de base</th>
                <td><strong>{{ number_format($contrat->salaire_base, 2, ',', ' ') }} €</strong></td>
            </tr>
            @if($contrat->poste)
            <tr>
                <th>Poste</th>
                <td>{{ $contrat->poste }}</td>
            </tr>
            @endif
            @if($contrat->departement)
            <tr>
                <th>Département</th>
                <td>{{ $contrat->departement }}</td>
            </tr>
            @endif
            <tr>
                <th>Référence</th>
                <td><code style="background: #F3F4F6; padding: 4px 8px; border-radius: 4px; font-size: 14px;">#{{ $contrat->id }}</code></td>
            </tr>
        </table>
    </div>
    
    @if($pdfUrl)
    <div class="alert alert-info">
        <h4>📄 Votre contrat en PDF</h4>
        <p>Vous pouvez télécharger votre contrat en format PDF en cliquant sur le lien ci-dessous :</p>
        <p style="text-align: center; margin-top: 15px;">
            <a href="{{ $pdfUrl }}" class="btn">📥 Télécharger le contrat (PDF)</a>
        </p>
    </div>
    @endif
    
    <div style="text-align: center;">
        <p>Vous pouvez consulter tous les détails de votre contrat dans votre espace personnel :</p>
        <p>
            <a href="{{ $contratUrl }}" class="btn">Voir mon contrat</a>
        </p>
    </div>
    
    <div class="alert alert-warning">
        <h4>⚠️ Important</h4>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Conservez une copie de votre contrat dans vos dossiers personnels</li>
            <li>Pour toute question concernant votre contrat, contactez le service des ressources humaines</li>
            <li>Les modifications de contrat doivent être demandées via votre manager</li>
        </ul>
    </div>
    
    <p>Nous vous souhaitons une excellente collaboration au sein de notre entreprise !</p>
    
    <p>Cordialement,<br>
    <strong>L'équipe des Ressources Humaines</strong></p>
@endsection
