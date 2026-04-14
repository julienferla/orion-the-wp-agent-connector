# Orion The WP Agent Connector

[![Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2Fjulienferla%2Forion-the-wp-agent-connector%2Fmain%2Fversion.json&query=%24.version&label=version&logo=wordpress&logoColor=white&color=21759b)](https://github.com/julienferla/orion-the-wp-agent-connector/blob/main/version.json)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Plugin WordPress connecteur pour [Orion The WP Agent](https://orionthewpagent.com)** — agent IA de gestion de contenu. Il expose une API REST sécurisée par jeton pour que votre site puisse être piloté depuis le tableau de bord Orion (articles, pages, médias, etc.).

## Installation

1. Téléchargez la dernière archive **`.zip`** depuis les [Releases GitHub](https://github.com/julienferla/orion-the-wp-agent-connector/releases).
2. Dans WordPress : **Extensions → Ajouter → Téléverser une extension**, choisissez le fichier `.zip`, puis **Installer** et **Activer**.
3. Allez dans **Réglages → Orion The WP Agent**, onglet **Connexion**.
4. Copiez le **jeton** et l’**URL du site**, puis collez-les dans votre espace Orion pour lier ce WordPress.

## Mises à jour

Les mises à jour sont **proposées automatiquement** dans le tableau de bord WordPress (**Extensions**), comme pour une extension hébergée hors répertoire officiel : WordPress interroge un manifeste JSON publié sur ce dépôt et télécharge le paquet depuis la dernière release.

Vous pouvez forcer une vérification depuis **Réglages → Orion The WP Agent → À propos** (« Vérifier les mises à jour ») ou vider le cache depuis l’onglet **Debug**.

## Releases

Toutes les versions et notes de publication :  
**https://github.com/julienferla/orion-the-wp-agent-connector/releases**

## Compatibilité

| Exigence | Version minimale |
|----------|------------------|
| WordPress | **5.8+** |
| PHP | **7.4+** |

## Orion The WP Agent

Connectez ce site à votre compte depuis le **tableau de bord SaaS** :  
**https://orionthewpagent.com**

(Adaptez l’URL si vous utilisez une instance ou un domaine personnalisé.)

## Développement

Ce dépôt contient uniquement le plugin. Le monorepo applicatif (Next.js, etc.) est distinct.

---

© Julien Ferla — Licence GPL-2.0-or-later
