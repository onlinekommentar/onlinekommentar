<?php

namespace App\Http\Controllers\Oai;

use App\Http\Controllers\Controller;
use App\Http\Resources\Oai\OaiResponseResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Statamic\Facades\Collection;

class OaiController extends Controller
{
    protected string $repositoryName = 'Onlinekommentar';

    protected string $adminEmail = 'daniel.brugger@onlinekommentar.ch';

    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = route('oai');
    }

    public function index(Request $request): Response
    {
        $verb = $request->input('verb');

        if (! $verb) {
            return OaiResponseResource::error('badVerb', 'Missing verb parameter', $request);
        }

        return match ($verb) {
            'Identify' => $this->identify($request),
            'ListMetadataFormats' => $this->listMetadataFormats($request),
            'ListSets' => $this->listSets($request),
            'ListIdentifiers' => $this->listIdentifiers($request),
            'ListRecords' => $this->listRecords($request),
            'GetRecord' => $this->getRecord($request),
            default => OaiResponseResource::error('badVerb', 'Invalid verb', $request),
        };
    }

    protected function identify(Request $request): Response
    {
        $commentariesCollection = Collection::findByHandle('commentaries');
        $earliestDatestamp = $commentariesCollection
            ->queryEntries()
            ->where('blueprint', 'commentary')
            ->whereStatus('published')
            ->orderBy('date')
            ->first()
            ?->date
            ?->format('Y-m-d') ?? '2020-01-01';

        $data = [
            'repositoryName' => $this->repositoryName,
            'baseURL' => $this->baseUrl,
            'protocolVersion' => '2.0',
            'adminEmail' => $this->adminEmail,
            'earliestDatestamp' => $earliestDatestamp,
            'deletedRecord' => 'no',
            'granularity' => 'YYYY-MM-DD',
        ];

        return OaiResponseResource::success('Identify', $data, $request);
    }

    protected function listMetadataFormats(Request $request): Response
    {
        $identifier = $request->input('identifier');

        if ($identifier) {
            $recordId = $this->extractRecordId($identifier);
            $commentariesCollection = Collection::findByHandle('commentaries');
            $entry = $commentariesCollection
                ->queryEntries()
                ->where('blueprint', 'commentary')
                ->whereStatus('published')
                ->where('id', $recordId)
                ->first();

            if (! $entry) {
                return OaiResponseResource::error('idDoesNotExist', 'Record not found', $request);
            }
        }

        $formats = [
            [
                'metadataPrefix' => 'oai_dc',
                'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
                'metadataNamespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
            ],
            [
                'metadataPrefix' => 'oai_openaire',
                'schema' => 'https://www.openaire.eu/schema/repo-lit/4.0/openaire.xsd',
                'metadataNamespace' => 'http://namespace.openaire.eu/schema/oaire/',
            ],
        ];

        return OaiResponseResource::success('ListMetadataFormats', ['formats' => $formats], $request);
    }

    protected function listSets(Request $request): Response
    {
        $resumptionToken = $request->input('resumptionToken');

        if ($resumptionToken && ! $this->isValidResumptionToken($resumptionToken)) {
            return OaiResponseResource::error('badResumptionToken', 'Invalid resumption token', $request);
        }

        $page = 1;
        $perPage = 100;

        if ($resumptionToken) {
            $tokenData = $this->decodeResumptionToken($resumptionToken);
            $page = $tokenData['page'] ?? 1;
        }

        $legalDomainsCollection = Collection::findByHandle('legal_domains');
        $legalDomains = $legalDomainsCollection
            ->queryEntries()
            ->get()
            ->filter(fn ($domain) => ! empty($domain->slug))
            ->map(fn ($domain) => [
                'setSpec' => 'legal_domain:'.$domain->slug,
                'setName' => $domain->title,
                'setDescription' => $domain->get('description'),
            ]);

        $sets = collect([
            [
                'setSpec' => 'openaire_data',
                'setName' => 'OpenAire Data Set',
                'setDescription' => 'All commentaries available for OpenAire harvesting',
            ],
        ])->merge($legalDomains);

        $paginated = new LengthAwarePaginator(
            $sets->forPage($page, $perPage),
            $sets->count(),
            $perPage,
            $page
        );

        $data = [
            'sets' => $paginated->items(),
            'resumptionToken' => $paginated->hasMorePages()
                ? $this->createResumptionToken($page + 1, $sets->count())
                : null,
            'resumptionTokenData' => $paginated->hasMorePages()
                ? [
                    'completeListSize' => $sets->count(),
                    'cursor' => ($page - 1) * $perPage,
                ]
                : null,
        ];

        return OaiResponseResource::success('ListSets', $data, $request);
    }

    protected function listIdentifiers(Request $request): Response
    {
        return $this->listRecordsOrIdentifiers($request, 'ListIdentifiers');
    }

    protected function listRecords(Request $request): Response
    {
        return $this->listRecordsOrIdentifiers($request, 'ListRecords');
    }

    protected function listRecordsOrIdentifiers(Request $request, string $verb): Response
    {
        $metadataPrefix = $request->input('metadataPrefix');
        $from = $request->input('from');
        $until = $request->input('until');
        $set = $request->input('set');
        $resumptionToken = $request->input('resumptionToken');

        if (! $metadataPrefix && ! $resumptionToken) {
            return OaiResponseResource::error('badArgument', 'Missing metadataPrefix', $request);
        }

        if ($metadataPrefix && ! in_array($metadataPrefix, ['oai_dc', 'oai_openaire'])) {
            return OaiResponseResource::error('cannotDisseminateFormat', 'Unsupported metadata format', $request);
        }

        if ($resumptionToken && ! $this->isValidResumptionToken($resumptionToken)) {
            return OaiResponseResource::error('badResumptionToken', 'Invalid resumption token', $request);
        }

        $page = 1;
        $perPage = 50;

        if ($resumptionToken) {
            $tokenData = $this->decodeResumptionToken($resumptionToken);
            $page = $tokenData['page'] ?? 1;
            $metadataPrefix = $tokenData['metadataPrefix'] ?? $metadataPrefix;
            $from = $tokenData['from'] ?? $from;
            $until = $tokenData['until'] ?? $until;
            $set = $tokenData['set'] ?? $set;
        }

        $commentariesCollection = Collection::findByHandle('commentaries');
        $query = $commentariesCollection
            ->queryEntries()
            ->whereStatus('published');

        if ($from) {
            $query->where('date', '>=', Carbon::parse($from));
        }

        if ($until) {
            $query->where('date', '<=', Carbon::parse($until));
        }

        if ($set && $set !== 'openaire_data') {
            if (str_starts_with($set, 'legal_domain:')) {
                $domainSlug = str_replace('legal_domain:', '', $set);
                $legalDomainsCollection = Collection::findByHandle('legal_domains');
                $domain = $legalDomainsCollection
                    ->queryEntries()
                    ->where('slug', $domainSlug)
                    ->first();

                if (! $domain) {
                    return OaiResponseResource::error('badArgument', 'Invalid set', $request);
                }

                $query->where('legal_domain', $domain->id());
            }
        }

        $paginator = $query->orderBy('date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        if ($paginator->isEmpty()) {
            return OaiResponseResource::error('noRecordsMatch', 'No records match the criteria', $request);
        }

        $data = [
            'records' => $paginator->items(),
            'metadataPrefix' => $metadataPrefix,
            'verb' => $verb,
            'resumptionToken' => $paginator->hasMorePages()
                ? $this->createResumptionToken($page + 1, $paginator->total(), [
                    'metadataPrefix' => $metadataPrefix,
                    'from' => $from,
                    'until' => $until,
                    'set' => $set,
                ])
                : null,
            'resumptionTokenData' => $paginator->hasMorePages()
                ? [
                    'completeListSize' => $paginator->total(),
                    'cursor' => ($page - 1) * $perPage,
                ]
                : null,
        ];

        return OaiResponseResource::success($verb, $data, $request);
    }

    protected function getRecord(Request $request): Response
    {
        $identifier = $request->input('identifier');
        $metadataPrefix = $request->input('metadataPrefix');

        if (! $identifier || ! $metadataPrefix) {
            return OaiResponseResource::error('badArgument', 'Missing required parameters', $request);
        }

        if (! in_array($metadataPrefix, ['oai_dc', 'oai_openaire'])) {
            return OaiResponseResource::error('cannotDisseminateFormat', 'Unsupported metadata format', $request);
        }

        $recordId = $this->extractRecordId($identifier);
        $commentariesCollection = Collection::findByHandle('commentaries');
        $entry = $commentariesCollection
            ->queryEntries()
            ->where('blueprint', 'commentary')
            ->whereStatus('published')
            ->where('id', $recordId)
            ->first();

        if (! $entry) {
            return OaiResponseResource::error('idDoesNotExist', 'Record not found', $request);
        }

        $data = [
            'record' => $entry,
            'metadataPrefix' => $metadataPrefix,
        ];

        return OaiResponseResource::success('GetRecord', $data, $request);
    }

    protected function extractRecordId(string $identifier): ?string
    {
        if (preg_match('/^oai:[^:]+:commentary:(.+)$/', $identifier, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function createOaiIdentifier(string $recordId): string
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST);

        return "oai:{$domain}:commentary:{$recordId}";
    }

    protected function createResumptionToken(int $page, int $total, array $params = []): string
    {
        $data = array_merge([
            'page' => $page,
            'total' => $total,
            'expires_at' => now()->addHour()->timestamp,
        ], $params);

        return base64_encode(json_encode($data));
    }

    protected function decodeResumptionToken(string $token): array
    {
        try {
            $decoded = json_decode(base64_decode($token), true);

            if (! is_array($decoded)) {
                return [];
            }

            return $decoded;
        } catch (\Exception) {
            return [];
        }
    }

    protected function isValidResumptionToken(string $token): bool
    {
        $data = $this->decodeResumptionToken($token);

        if (empty($data)) {
            return false;
        }

        $expiresAt = $data['expires_at'] ?? 0;

        return $expiresAt > now()->timestamp;
    }
}
