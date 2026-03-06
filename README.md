# Alchemistic

User and service management portal for Istic Hosting.

## GitHub Workflows

Three workflows run on this repository: `tests`, `linter`, and `Build and publish Docker image`.

### Repository Variables

Set these under **Settings → Secrets and variables → Variables**:

| Variable | Used by | Description |
|---|---|---|
| `PHP_VERSION` | `docker.yml`, `lint.yml` | PHP version to use (e.g. `8.4`) |
| `NODE_VERSION` | `docker.yml`, `tests.yml` | Node.js version to use (e.g. `22`) |

### Environments

The `tests` and `linter` workflows use the **Testing** environment. No environment-specific variables are required beyond the repository variables above.

### Releases

Releases are created via the **Create Release Tag** workflow (`workflow_dispatch`). It runs the test suite first and only creates a tag if tests pass. Creating a tag triggers the Docker build workflow, which builds and pushes the image to `ghcr.io`.
