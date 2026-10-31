#!/usr/bin/env python3
"""Actions-Minuten dieses Monats je Workflow (Free Tier: 2.000 Minuten).

Zählt die Laufzeit jedes Jobs, der auf einem GitHub-Runner lief, auf volle
Minuten aufgerundet (so rechnet GitHub ab). Jobs auf dem eigenen NAS-Runner
zählen nicht. Andere private Repositories desselben Kontos verbrauchen
dasselbe Kontingent und fehlen hier; den genauen Stand zeigt GitHub unter
Settings → Billing → Usage.

Aufruf: python3 scripts/actions-minuten.py [JJJJ-MM]   (braucht GH_TOKEN oder gh)
"""
import datetime
import json
import math
import os
import subprocess
import sys
from collections import defaultdict

REPO = os.environ.get('GITHUB_REPOSITORY', 'son1x/kluft')
KONTINGENT = 2000


def api(pfad):
    token = os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN')
    kopf = ['-H', 'Authorization: Bearer ' + token] if token else []
    aus = subprocess.run(['curl', '-fsS', *kopf, '-H', 'Accept: application/vnd.github+json',
                          'https://api.github.com/repos/' + REPO + pfad], capture_output=True, text=True, check=True).stdout
    return json.loads(aus)


def zeit(s):
    return datetime.datetime.fromisoformat(s.replace('Z', '+00:00'))


monat = sys.argv[1] if len(sys.argv) > 1 else datetime.date.today().strftime('%Y-%m')
laeufe = []
for seite in range(1, 11):
    teil = api(f'/actions/runs?per_page=100&page={seite}&created=>={monat}-01').get('workflow_runs', [])
    laeufe += [r for r in teil if r['created_at'].startswith(monat)]
    if len(teil) < 100:
        break

summe = defaultdict(lambda: [0, 0])
for lauf in laeufe:
    summe[lauf['name']][0] += 1
    for job in api(f"/actions/runs/{lauf['id']}/jobs?per_page=100").get('jobs', []):
        eigener = 'self-hosted' in (job.get('labels') or [])
        if job.get('runner_id') and job.get('started_at') and job.get('completed_at') and not eigener:
            summe[lauf['name']][1] += math.ceil((zeit(job['completed_at']) - zeit(job['started_at'])).total_seconds() / 60)

gesamt = sum(m for _, m in summe.values())
print(f'Actions-Minuten {monat} ({REPO}, nur GitHub-Runner):')
for name, (anzahl, minuten) in sorted(summe.items(), key=lambda x: -x[1][1]):
    print(f'  {name:36} {anzahl:4} Läufe {minuten:6} min')
print(f'  {"Summe":36} {len(laeufe):4} Läufe {gesamt:6} min von {KONTINGENT} ({gesamt * 100 // KONTINGENT} %)')
