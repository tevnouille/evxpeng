<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client pour l'API Xpeng Open Platform (`/oauth2/queryData`).
 *
 * Ce n'est pas une API de telemetrie en direct : un premier appel soumet une
 * tache d'export cote Xpeng, les appels suivants en interrogent l'etat via un
 * recordNo mis en cache de leur cote (jamais expose ici) jusqu'a l'obtention
 * d'une URL de telechargement, valable ~30 secondes seulement. Quota strict :
 * 5 soumissions par 24h et par couple utilisateur-entreprise -- c'est
 * App\Console\Commands\SyncXpengData qui decide du rythme des appels, jamais
 * une requete HTTP entrante (voir XpengDataController).
 *
 * Algorithme de signature (section 9 du guide d'integration) : seules deux
 * cles participent, "body" (le JSON du corps, tel quel, pas re-serialise) et
 * "nonce" -- pas chaque champ du corps individuellement. La chaine signee
 * doit donc utiliser exactement les octets envoyes comme corps de requete.
 */
class XpengClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $openId,
        private readonly string $accessToken,
        private readonly string $enterpriseName,
        private readonly string $scopeCode,
    ) {
    }

    /**
     * Soumet la tache d'export (premier appel) ou interroge son etat (appels
     * suivants) -- l'API ne distingue pas les deux cas cote appelant.
     *
     * @return array{code: int, data: ?string, msg: ?string, desc: ?string}
     */
    public function queryData(): array
    {
        $body = json_encode([
            'openId' => $this->openId,
            'accessToken' => $this->accessToken,
            'enterpriseName' => $this->enterpriseName,
            'scopeCode' => $this->scopeCode,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $nonce = (string) (int) round(microtime(true) * 1000);

        // stringA = "body" + <corps tel quel> + "nonce" + <nonce> -- ordre
        // alphabetique des deux seules cles qui participent a la signature.
        $stringA = 'body'.$body.'nonce'.$nonce;
        $stringB = $this->appId.$stringA.$this->appSecret;
        $sign = strtolower(sha1($stringB));

        $query = http_build_query([
            'appId' => $this->appId,
            'nonce' => $nonce,
            'sign' => $sign,
        ]);

        $reponse = Http::timeout(15)
            ->withBody($body, 'application/json')
            ->post($this->baseUrl.'?'.$query);

        $decoded = $reponse->json();

        if (! is_array($decoded) || ! array_key_exists('code', $decoded)) {
            return [
                'code' => -1,
                'data' => null,
                'msg' => null,
                'desc' => 'Reponse HTTP '.$reponse->status().' non JSON ou inattendue.',
            ];
        }

        return [
            'code' => (int) $decoded['code'],
            'data' => $decoded['data'] ?? null,
            'msg' => $decoded['msg'] ?? null,
            'desc' => $decoded['desc'] ?? null,
        ];
    }
}
