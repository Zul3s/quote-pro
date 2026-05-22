<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bienvenue sur Quote Plus</title>
</head>
<body>
    <h1>Bonjour {{ $firstName ?? $email }} !</h1>
    <p>Bienvenue sur Quote Plus, votre nouvel espace de gestion de devis.</p>
    <p>Votre compte ({{ $email }}) est maintenant prêt à l'emploi.</p>
</body>
</html>
