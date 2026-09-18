<?php
/**
 * OAuth authorization consent screen (opt-in).
 *
 * Extends the Tessera-style consent markup with `logo_uri` / `client_uri`
 * blocks, both null-guarded so clients without those attributes render
 * the fallback. Styling stays Tessera-plain on purpose: no extra stack,
 * less to maintain, survives Tessera updates.
 *
 * Nothing wires this template automatically. Enable it with:
 * `Tessera::authorizationView('Mcp.Authorization/authorize')`
 * or copy it to the host app at
 * `templates/plugin/Tessera/Authorization/authorize.php`
 * (the host override wins by Cake convention, no code needed).
 *
 * @var \Cake\View\View $this
 * @var \Crustum\Tessera\Model\Entity\Client $client
 * @var \Crustum\Tessera\Contracts\OAuthenticatable $user
 * @var list<\Crustum\Tessera\Scope> $scopes
 * @var string $authToken
 */
$logoUri = $client->logo_uri ?? null;
$clientUri = $client->client_uri ?? null;
$userEmail = '';
if (is_object($user)) {
    $candidate = $user->email ?? null;
    if ($candidate === null && method_exists($user, 'getIdentifier')) {
        $identifier = $user->getIdentifier();
        $candidate = is_scalar($identifier) ? (string)$identifier : null;
    }
    $userEmail = (string)($candidate ?? '');
} elseif (is_array($user)) {
    $userEmail = (string)($user['email'] ?? '');
}
?>
<div class="tessera authorization consent">
    <?php if (!empty($logoUri)) : ?>
    <img src="<?= h($logoUri) ?>" alt="<?= h((string)($client->name ?? '')) ?>">
    <?php else : ?>
    <svg class="h-12 w-12 text-primary" width="48" height="48" style="width:48px;height:48px;" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
    </svg>
    <?php endif; ?>
    <h3><?= h(__('Authorization consent')) ?></h3>
    <p>
        <?= h(__('{0} is requesting access to your account.', $client->name ?? '')) ?>
    </p>
    <?php if (!empty($clientUri)) : ?>
    <p>
        <a href="<?= h($clientUri) ?>" target="_blank" rel="noopener noreferrer"><?= h($clientUri) ?></a>
    </p>
    <?php endif; ?>
    <p>
        <?= h(__('Logged in as {0}', $userEmail)) ?>
    </p>
    <?php if (!empty($scopes)): ?>
        <ul>
            <?php foreach ($scopes as $scope): ?>
                <li><?= h($scope->description !== '' ? $scope->description : $scope->id) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?= $this->Form->create(null, ['url' => ['_name' => 'tessera:authorizations.approve']]) ?>
        <?= $this->Form->hidden('auth_token', ['value' => $authToken]) ?>
        <?= $this->Form->button(__('Approve'), ['name' => 'approve']) ?>
    <?= $this->Form->end() ?>
</div>
