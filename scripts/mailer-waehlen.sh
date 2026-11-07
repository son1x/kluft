#!/usr/bin/env bash
# Welcher Mailer kommt in die .env? (Deploy, docs/deploy.md „Mail“)
#
# Zeile 1: smtp oder log. Zeile 2 (nur wenn nötig): Hinweis für das
# Deploy-Protokoll. Liest aus der Umgebung: BREVO_SMTP_LOGIN, BREVO_SMTP_KEY,
# MAIL_TEST_REDIRECT_TO, KLUFT_TESTSYSTEM, KLUFT_MAIL_LIVE.
#
# Regeln (sicher per Voreinstellung, F-MAIL-06):
#   - ohne Brevo-SMTP-Zugang: log
#   - mit Test-Umleitung: smtp, alles geht an die Testadresse (außer Freigabeliste)
#   - Testsystem (KLUFT_TESTSYSTEM gesetzt) ohne Umleitung: log
#   - Live-Seite: smtp nur mit ausdrücklichem KLUFT_MAIL_LIVE=ja
#   - sonst: log
set -euo pipefail

trim() { local v="${1:-}"; v="${v#"${v%%[![:space:]]*}"}"; printf '%s' "${v%"${v##*[![:space:]]}"}"; }

login="$(trim "${BREVO_SMTP_LOGIN:-}")"
key="$(trim "${BREVO_SMTP_KEY:-}")"
umleitung="$(trim "${MAIL_TEST_REDIRECT_TO:-}")"
testsystem="$(trim "${KLUFT_TESTSYSTEM:-}")"
live="$(trim "${KLUFT_MAIL_LIVE:-}" | tr '[:upper:]' '[:lower:]')"

if [ -z "$login" ] || [ -z "$key" ]; then
  printf 'log\nMails gehen nur ins Log: Secrets BREVO_SMTP_LOGIN und BREVO_SMTP_KEY fehlen.\n'
elif [ -n "$umleitung" ]; then
  printf 'smtp\n'
elif [ -n "$testsystem" ]; then
  printf 'log\nMails gehen nur ins Log: Testsystem (KLUFT_TESTSYSTEM) ohne Test-Umleitung. Für Testmails das Secret MAIL_TEST_REDIRECT_TO setzen.\n'
else
  case "$live" in
    ja|true|1|yes) printf 'smtp\n' ;;
    *) printf 'log\nMails gehen nur ins Log: Für eine Live-Seite die Variable KLUFT_MAIL_LIVE=ja setzen, für ein Testsystem das Secret MAIL_TEST_REDIRECT_TO.\n' ;;
  esac
fi
