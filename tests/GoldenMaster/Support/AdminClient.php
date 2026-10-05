<?php

namespace Lmo\Tests\GoldenMaster\Support;

/**
 * Simuliert einen Browser im Admin-Bereich: behaelt die Sitzung ueber mehrere Anfragen
 * und meldet nach jedem Schritt, welche Dateien sich geaendert haben.
 */
final class AdminClient
{
    private Instance $instance;
    private Runner $runner;
    private string $sid = '';
    private string $lastRaw = '';
    private string $lastScript = 'lmoadmin.php';
    private string $lastNormalized = '';
    /** @var array<string,string> */
    private array $state;

    public function __construct(Instance $instance, Runner $runner)
    {
        $this->instance = $instance;
        $this->runner = $runner;
        $this->state = StateTracker::read($instance->path());
    }

    public function get(string $script, string $query = ''): string
    {
        return $this->request('GET', $script, $query, []);
    }

    /** @param array<string,mixed> $post */
    public function post(string $script, string $query, array $post): string
    {
        return $this->request('POST', $script, $query, $post);
    }

    /**
     * Ruft die Seite ab, fuellt das Formular wie ein Browser aus (Standardwerte + Abweichungen)
     * und schickt es ab. Rueckgabe: gesendete Felder und normalisierte Antwort.
     *
     * @param array<string,string|int|array<int,string|int>> $overrides
     */
    public function submitForm(string $script, string $query, ?string $mustContain, array $overrides = [], ?string $submit = null): string
    {
        $this->request('GET', $script, $query, []);
        return $this->submitFromLast($mustContain, $overrides, $submit);
    }

    /**
     * Wie submitForm, aber das Formular stammt aus der letzten Antwort (mehrstufige Ablaeufe).
     *
     * @param array<string,string|int|array<int,string|int>> $overrides
     */
    public function submitFromLast(?string $mustContain, array $overrides = [], ?string $submit = null): string
    {
        $form = FormSubmitter::extract($this->lastRaw, $mustContain, $submit);
        if ($form === null) {
            return "### KEIN FORMULAR GEFUNDEN (Feld: " . ($mustContain ?? 'save') . ")\n"
                . "### Antwort der vorigen Seite:\n" . $this->lastNormalized;
        }
        if ($form['fields'] === []) {
            return "### LEERES FORMULAR (Formulare auf der Seite: {$form['formCount']})\n" . $this->lastNormalized;
        }
        $fields = FormSubmitter::override($form['fields'], $overrides);
        $header = "### GESENDETE FELDER (" . strtoupper($form['method']) . ", " . count($fields) . ")\n"
            . FormSubmitter::describe($fields) . "\n### ANTWORT\n";

        if ($form['method'] === 'post') {
            return $header . $this->request('POST', $this->lastScript, '', [], FormSubmitter::encode($fields));
        }
        return $header . $this->request('GET', $this->lastScript, FormSubmitter::encode($fields), []);
    }

    public function login(string $user, string $password): string
    {
        return $this->post('lmoadmin.php', '', [
            'action' => 'admin',
            'xusername' => $user,
            'xuserpass' => $password,
            'xusersub' => 'Login',
        ]);
    }

    /** Dateiaenderungen seit dem letzten Aufruf (oder seit Beginn). Leer = nichts geaendert. */
    public function changes(): string
    {
        $now = StateTracker::read($this->instance->path());
        $report = StateTracker::diff($this->state, $now, $this->instance->path());
        $this->state = $now;
        return $report;
    }

    /** Baseline neu setzen, z. B. nachdem der Test selbst Dateien vorbereitet hat. */
    public function resetChanges(): void
    {
        $this->state = StateTracker::read($this->instance->path());
    }

    public function instance(): Instance
    {
        return $this->instance;
    }

    /**
     * @param array<string,mixed> $post
     */
    private function request(string $method, string $script, string $query, array $post, ?string $postRaw = null): string
    {
        $spec = [
            'script' => $script,
            'query' => $query,
            'method' => $method,
            'post' => $post,
            'sid' => $this->sid,
        ];
        if ($postRaw !== null) {
            $spec['postRaw'] = $postRaw;
        }
        $result = $this->runner->http($spec);
        if ($result['sid'] !== '') {
            $this->sid = $result['sid'];
        }
        $this->lastRaw = $result['body'];
        $this->lastScript = $script;
        $this->lastNormalized = Normalizer::admin($result['body'], $this->instance->path(), $query);
        return $this->lastNormalized;
    }
}
