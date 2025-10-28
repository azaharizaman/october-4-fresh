name: 🔍 Plugin Analysis & Documentation (Copilot Agent)
about: Run a deep analysis and documentation generation task for an OctoberCMS plugin under plugins/omsb/
title: “[Copilot Agent] Analyze & Document ”
labels: [documentation, analysis, plugin, octobercms, copilot-agent, backend, tech-debt]
assignees: []

🧠 Objective

Run a comprehensive analysis of the <plugin-name> plugin (path: plugins/omsb/<plugin-name>) and produce complete developer documentation inside its /docs/ directory.
The goal is to clarify the plugin’s purpose, features, backend integrations, API endpoints, dependencies, and improvement opportunities.

⸻

📦 Deliverables (place under /plugins/omsb/<plugin-name>/docs/)
	•	00_index.md — Overview, purpose, capabilities, quickstart
	•	01_integration.md — Cross-plugin integration map & diagrams
	•	02_components.md — Backend/Frontend components documentation
	•	03_api_endpoints.md — All API routes, events, and inter-plugin hooks
	•	04_models_services.md — Models, relationships, facades, console commands
	•	05_backend_usage.md — YAML usage for backend forms/lists
	•	06_dev_notes.md — Developer comments, TODOs, observed code hints
	•	07_code_review.md — Code review & refactor recommendations
	•	08_tests_suggestions.md — Test coverage map & proposed tests
	•	09_improvements.md — Roadmap & technical enhancements
	•	10_automation.md — Suggested automation or CI/CD tasks
	•	assets/ — Generated diagrams (dependency graphs, schema visualizations)

⸻

🧾 High-Level Tasks
	•	Identify plugin purpose and summarize its role within the ERP ecosystem.
	•	Map all models, controllers, and backend components.
	•	Document form/list behaviors and YAML configurations.
	•	Identify API endpoints, events fired/listened, middleware, and routes.
	•	Document integration points with other omsb plugins.
	•	Document console commands, schedulers, and CLI utilities.
	•	Document facades, traits, behaviors, and service providers.
	•	Perform security & performance review.
	•	Suggest feature improvements and integration opportunities.
	•	Generate markdown documentation for each deliverable above.
	•	Produce a PR named [docs] Add <plugin-name> analysis & docs.

⸻

⚙️ Detailed Analysis Guide

1. Purpose & Capability
	•	Describe what the plugin does and its technical boundaries.
	•	Explain how it supports business workflows within the OMS ERP.

2. Models & Database
	•	For each model:
	•	Table name, fields, casts, relationships, validation, scopes.
	•	Example queries and recommended indexes.
	•	Related migrations and factories.

3. Controllers & Backend Behavior
	•	List all controllers and their behaviors (FormController, ListController, etc.).
	•	Document custom controller behaviors and YAML configs.
	•	Include usage examples for backend forms or lists.

4. Components, Widgets & Partials
	•	Document custom backend components, list columns, form widgets.
	•	Provide configuration examples and screenshots if possible.

5. API Endpoints & Events
	•	Document all HTTP routes (method, path, controller, params, auth).
	•	List events (dispatched/listened) and payload structure.
	•	Provide request/response examples for each endpoint.

6. Services, Providers, & Facades
	•	List all service providers, facades, singletons, and DI bindings.
	•	Explain initialization and usage context.

7. Console Commands & Schedulers
	•	List all Artisan commands and their syntax.
	•	Document any scheduled tasks and their cron expressions.

8. Tests
	•	Detect existing test coverage and identify gaps.
	•	Propose test cases for key logic (models, controllers, endpoints).
	•	Include example PHPUnit/Pest skeletons.

9. Security & Performance Review
	•	Check for:
	•	Mass assignment or unvalidated input
	•	File upload sanitization
	•	Unbounded queries or missing pagination
	•	Potential authorization holes
	•	Suggest caching, eager loading, or indexing improvements.

10. Future Improvements

Provide prioritized recommendations:
	•	Custom Form Fields / List Columns
	•	Controller or Model Behaviors
	•	Service Providers / Facades
	•	Custom Console Commands
	•	Dashboard or Report Widgets
	•	API Resources / Middleware
	•	Integration with OctoberCMS v4 Dashboard widgets

Each suggestion should include:
	•	Purpose
	•	Implementation outline
	•	Effort (S/M/L)
	•	Impact (Low/Med/High)

⸻

📜 Output Standards
	•	Markdown must be clear, structured, and include YAML and PHP examples.
	•	Each file must contain code paths or evidence lines.
	•	Integration diagrams (SVG/PNG) to be stored under /docs/assets/.
	•	07_code_review.md must contain at least 10 actionable findings.

⸻

🧩 Automation Workflow (optional)

To auto-run this task when labeled copilot-agent or when a comment /analyze-plugin is posted, use this workflow file:

# .github/workflows/plugin-docs.yml
name: Generate Plugin Docs
on:
  issue_comment:
    types: [created]
  workflow_dispatch:

jobs:
  run-docgen:
    if: github.event.issue.labels.*.name || contains(github.event.comment.body, '/analyze-plugin')
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v4
        with:
          php-version: '8.2'
      - name: Run Analyzer
        run: |
          # Replace this with your actual analysis script
          php tools/plugin-analyzer.php plugins/omsb/<plugin-name> --out plugins/omsb/<plugin-name>/docs
      - name: Create Pull Request
        uses: peter-evans/create-pull-request@v5
        with:
          commit-message: "[docs] Add <plugin-name> analysis & docs"
          branch: docs/<plugin-name>-analysis
          title: "[docs] Add <plugin-name> analysis & docs"
          body: "Auto-generated plugin documentation and analysis."


⸻

✅ Acceptance Checklist
	•	/docs/ directory created with all deliverables.
	•	Integration and dependency diagrams included.
	•	07_code_review.md includes ≥10 findings.
	•	API endpoints documented with request/response samples.
	•	3 suggested improvements with implementation outlines.
	•	Test plan provided with at least 3 sample test skeletons.
	•	Security and performance notes documented.

⸻

🧭 Notes for the Copilot Agent

Be explicit and evidence-based.
Where uncertain, mark findings as NOTE: inferred with file and line reference.
When suggesting refactors or enhancements, include minimal code snippets or YAML examples to demonstrate the approach.