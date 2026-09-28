# Local development image. Source code is bind-mounted at runtime (see docker-compose.yml
# at the repo root) so edits on the host are picked up immediately — this image only
# provides the Node runtime; dependencies are installed by docker/entrypoint.sh on first
# start (or reused if frontend/node_modules already exists from a native `npm install`).
#
# Debian-based (glibc), not Alpine (musl): Next.js's native addons (SWC, Turbopack) are
# glibc binaries, and node_modules is bind-mounted from the host (also glibc) so the two
# need matching libc to load the same installed binaries.
FROM node:22-bookworm-slim

WORKDIR /app

EXPOSE 3000

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["npm", "run", "dev", "--", "--port", "3000", "--hostname", "0.0.0.0"]
