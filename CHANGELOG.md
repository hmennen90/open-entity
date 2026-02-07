## [1.7.3](https://github.com/hmennen90/open-entity/compare/v1.7.2...v1.7.3) (2026-02-07)


### Bug Fixes

* think worker loop fixed ([d7887ca](https://github.com/hmennen90/open-entity/commit/d7887ca126bf9cb913a21eb781691b22c5aa71f8))

## [1.7.2](https://github.com/hmennen90/open-entity/compare/v1.7.1...v1.7.2) (2026-02-06)


### Bug Fixes

* think worker loop fixed ([b13a954](https://github.com/hmennen90/open-entity/commit/b13a954997d7c367b7c097a4f4bf354a8842b13b))

## [1.7.1](https://github.com/hmennen90/open-entity/compare/v1.7.0...v1.7.1) (2026-02-06)


### Bug Fixes

* **entity:** Prevent think loop stalling and fix i18n for wake/sleep messages ([9d8c3bc](https://github.com/hmennen90/open-entity/commit/9d8c3bc7eb8ab5dd866f6e99baa859b5a62312f7))

# [1.7.0](https://github.com/hmennen90/open-entity/compare/v1.6.0...v1.7.0) (2026-02-05)


### Bug Fixes

* **goals:** Auto-complete goals at 100% progress and add cleanup command ([7c6261d](https://github.com/hmennen90/open-entity/commit/7c6261d89b3ef3e2a4bf789100d3a05f44bc37bd))


### Features

* **entity:** Add autonomous wake-up capabilities ([139cd19](https://github.com/hmennen90/open-entity/commit/139cd19329351337dc977e2154d4518b5b5a074e))
* **goals:** Add learnings tracking to goals ([c8aa278](https://github.com/hmennen90/open-entity/commit/c8aa278485e9b1cb8fca5ef9a1f571fe2e3fa7a1))

# [1.6.0](https://github.com/hmennen90/open-entity/compare/v1.5.0...v1.6.0) (2026-02-05)


### Bug Fixes

* **docker:** Update native-ollama override with new worker names ([3ca0553](https://github.com/hmennen90/open-entity/commit/3ca0553b564aba3d0a311a8bd16fc3a676a6e8f5))


### Features

* **docker:** Add dedicated think-loop worker with adaptive intervals ([965bade](https://github.com/hmennen90/open-entity/commit/965bade00dffcbf063dca3cdceb7c09067adfc22))
* **think:** Add adaptive think intervals based on activity ([12761b6](https://github.com/hmennen90/open-entity/commit/12761b65f954bcf86c1bd18e9c8ea23da02ee068))

# [1.5.0](https://github.com/hmennen90/open-entity/compare/v1.4.1...v1.5.0) (2026-02-05)


### Bug Fixes

* **ci:** Explicitly set CACHE_DRIVER=array for test isolation ([198ffd9](https://github.com/hmennen90/open-entity/commit/198ffd9266aea9cc4790320431de5000bed97979))
* **ci:** Remove CACHE_DRIVER override to fix test isolation ([2e5cdb9](https://github.com/hmennen90/open-entity/commit/2e5cdb93118dda3dab3d19097a524e39e8d55732))
* **test:** Add .env.testing and simplify dream test assertion ([9cb7886](https://github.com/hmennen90/open-entity/commit/9cb788642e2e4a2c3d41d70437cfc9ab57a6f0ff))
* **test:** Add Reverb config to .env.testing ([1ac9535](https://github.com/hmennen90/open-entity/commit/1ac9535077ced2becda080c0991c4b96691d0191))


### Features

* Add dream thoughts while sleeping and update notifications ([374684b](https://github.com/hmennen90/open-entity/commit/374684b097ba0c543d3c265c404d14adc33d9357))

## [1.4.1](https://github.com/hmennen90/open-entity/compare/v1.4.0...v1.4.1) (2026-02-05)


### Bug Fixes

* **events:** Use ShouldBroadcastNow for immediate UI updates ([7572809](https://github.com/hmennen90/open-entity/commit/7572809d7c286eff161d95d5c7c1b92b898acc79))

# [1.4.0](https://github.com/hmennen90/open-entity/compare/v1.3.1...v1.4.0) (2026-02-05)


### Features

* **tools:** Add GoalTool with duplicate detection and improve question routing ([3ccead5](https://github.com/hmennen90/open-entity/commit/3ccead513d029ea7a072a87a74e2e307e6658f9b))

## [1.3.1](https://github.com/hmennen90/open-entity/compare/v1.3.0...v1.3.1) (2026-02-05)


### Bug Fixes

* **docker:** Fix Windows line ending issues for entrypoint scripts ([ceb91a5](https://github.com/hmennen90/open-entity/commit/ceb91a5a9b266125fe7ac99239ef6ab50716e266))

# [1.3.0](https://github.com/hmennen90/open-entity/compare/v1.2.0...v1.3.0) (2026-02-05)


### Features

* **config:** Add ENTITY_LANGUAGE config and UserPreferencesTool ([47401c3](https://github.com/hmennen90/open-entity/commit/47401c3c9677725d7f6f3eed4ea355b3bdd5780d))

# [1.2.0](https://github.com/hmennen90/open-entity/compare/v1.1.0...v1.2.0) (2026-02-05)


### Features

* **tools:** Add SearchTool with page fetching and UpdateCheckTool ([8a9dae5](https://github.com/hmennen90/open-entity/commit/8a9dae567b073ea3f22f36b3e4486c35fb9a26d4))

# [1.1.0](https://github.com/hmennen90/open-entity/compare/v1.0.2...v1.1.0) (2026-02-05)


### Bug Fixes

* **tests:** disable SearchTool in ToolRegistryTest setup ([4c77b87](https://github.com/hmennen90/open-entity/commit/4c77b87e327ef92f9172a9c8e1cabcc394d499f2))


### Features

* **tools:** add SearchTool for web searches via DuckDuckGo ([b921ba7](https://github.com/hmennen90/open-entity/commit/b921ba7794e4d95bf5b80096a23768aa61f88d06))

## [1.0.2](https://github.com/hmennen90/open-entity/compare/v1.0.1...v1.0.2) (2026-02-05)


### Bug Fixes

* add REVERB_SERVER_HOST for Docker container-to-container communication ([2a4d0ca](https://github.com/hmennen90/open-entity/commit/2a4d0caf5532c329da76c2c5befb23ec1350cf9d))

## [1.0.1](https://github.com/hmennen90/open-entity/compare/v1.0.0...v1.0.1) (2026-02-05)


### Bug Fixes

* **ci:** use explicit COMPOSE_PROJECT_NAME for Docker image naming ([258cfe2](https://github.com/hmennen90/open-entity/commit/258cfe2877f54f4ef9011912539a268546caac2b))


### Performance Improvements

* **ci:** reuse Docker images across integration test jobs ([2c73682](https://github.com/hmennen90/open-entity/commit/2c7368272d173833194010c1ff5166679b87caea))

# 1.0.0 (2026-02-05)


* feat!: initial open source release ([407e066](https://github.com/hmennen90/open-entity/commit/407e066457c05dba40cf74b602d3f281cad75792))


### Bug Fixes

* **ci:** capture all PowerShell streams in Windows setup tests ([60e7b98](https://github.com/hmennen90/open-entity/commit/60e7b9819afba806b6d4c6dae1e9e70c8cbf144c))
* **ci:** configure Ollama to listen on all interfaces ([6a3c902](https://github.com/hmennen90/open-entity/commit/6a3c902604f5769f9fedfff480c0a12424ec3c35))
* **ci:** downgrade PHP requirement to ^8.2 for CI compatibility ([f4d4333](https://github.com/hmennen90/open-entity/commit/f4d433364c06d72adcf89cd33c6cd6bd29613f51))
* **ci:** fix reverb health check and Linux host.docker.internal ([0a438a1](https://github.com/hmennen90/open-entity/commit/0a438a1a9e7fda98744beebb0bbdb323242d860d))
* **ci:** properly export OLLAMA_HOST for Ollama subprocess ([6f41fa3](https://github.com/hmennen90/open-entity/commit/6f41fa3780bd24ac113ed78b1a48914b36b0d4e0))
* **ci:** properly handle volume ownership for Docker integration tests ([3ad0938](https://github.com/hmennen90/open-entity/commit/3ad09385e95516bec5aed5c73a5a52e5f29a8c5a))
* **ci:** resolve permission errors in Docker integration tests ([43ce387](https://github.com/hmennen90/open-entity/commit/43ce3872caab53e4eb6d79295231466d0b5ab5cd))
* **ci:** set broadcast env vars before composer install ([0665467](https://github.com/hmennen90/open-entity/commit/066546781cdc16a657d6ad7424a5e6e036029c25))
* **ci:** stagger container startup to avoid healthcheck race condition ([9c5bda4](https://github.com/hmennen90/open-entity/commit/9c5bda414cf716230f9bb0495b9c4d68753c59ea))
* **ci:** stop systemd Ollama service before manual start with 0.0.0.0 binding ([7363748](https://github.com/hmennen90/open-entity/commit/7363748c508c5608ae1f1931325465629ef6de18))
* **ci:** strip ANSI escape codes for reliable CI test matching ([1706b9a](https://github.com/hmennen90/open-entity/commit/1706b9a029a1a41df473bc664fb56eba61377bea))
* **ci:** update macOS runner from retired macos-13 to macos-15 ([a088fa1](https://github.com/hmennen90/open-entity/commit/a088fa120830cd8226a195c80536f2f3981dc924))
* **docker:** use PHP 8.3 image (8.5 doesn't exist yet) ([a1d239b](https://github.com/hmennen90/open-entity/commit/a1d239bb2f88d168fcb88c3191b048b45c72dea9))
* Fixed Front-end Building ([9c5c6b7](https://github.com/hmennen90/open-entity/commit/9c5c6b788bc340609d5f00d848e254a1abbe1f83))
* Fixed Windows CI ([68971fc](https://github.com/hmennen90/open-entity/commit/68971fc754209e2c36e7aa30a530da45274a9068))
* Ollama has to run on Host Machine for GPU Support. Removed it's docker service ([87a650c](https://github.com/hmennen90/open-entity/commit/87a650c4ba8915c93a93361c845818ac5440c5bb))
* Resolved Problems with Windows Startup ([1ed954d](https://github.com/hmennen90/open-entity/commit/1ed954d604869ef16e2a1b1577348f6d7909ce4a))
* Resolved Reverb Startup ([a0e4f29](https://github.com/hmennen90/open-entity/commit/a0e4f291dd1f5d7a94680853e6934a825a3fbcdf))
* **tests:** adjust assertions for CI environment ([1e01275](https://github.com/hmennen90/open-entity/commit/1e0127560a30d310658cc610677caffda484a533))
* **tests:** use factory for memory creation and robust ordering check ([717bcc2](https://github.com/hmennen90/open-entity/commit/717bcc2b1929e09553c01f305ded16021a5a71c8))


### BREAKING CHANGES

* Initial public release of OpenEntity as open source software.

- Added MIT License
- Complete documentation suite (docs/, CLAUDE.md)
- 139 passing tests with PHP 8.4+ SQLite compatibility
- Semantic release workflow for automated versioning
- Contribution guidelines (CONTRIBUTING.md)
- Security notes for Docker isolation
- PayPal donation link
- License compliance documentation

## [1.0.3](https://github.com/hmennen90/open-entity/compare/v1.0.2...v1.0.3) (2026-02-05)


### Bug Fixes

* Fixed Front-end Building ([9c5c6b7](https://github.com/hmennen90/open-entity/commit/9c5c6b788bc340609d5f00d848e254a1abbe1f83))

## [1.0.2](https://github.com/hmennen90/open-entity/compare/v1.0.1...v1.0.2) (2026-02-05)


### Bug Fixes

* Resolved Reverb Startup ([a0e4f29](https://github.com/hmennen90/open-entity/commit/a0e4f291dd1f5d7a94680853e6934a825a3fbcdf))

## [1.0.1](https://github.com/hmennen90/open-entity/compare/v1.0.0...v1.0.1) (2026-02-05)


### Bug Fixes

* Resolved Problems with Windows Startup ([1ed954d](https://github.com/hmennen90/open-entity/commit/1ed954d604869ef16e2a1b1577348f6d7909ce4a))

# 1.0.0 (2026-02-04)


* feat!: initial open source release ([407e066](https://github.com/hmennen90/open-entity/commit/407e066457c05dba40cf74b602d3f281cad75792))


### Bug Fixes

* **ci:** set broadcast env vars before composer install ([0665467](https://github.com/hmennen90/open-entity/commit/066546781cdc16a657d6ad7424a5e6e036029c25))
* **tests:** adjust assertions for CI environment ([1e01275](https://github.com/hmennen90/open-entity/commit/1e0127560a30d310658cc610677caffda484a533))
* **tests:** use factory for memory creation and robust ordering check ([717bcc2](https://github.com/hmennen90/open-entity/commit/717bcc2b1929e09553c01f305ded16021a5a71c8))


### BREAKING CHANGES

* Initial public release of OpenEntity as open source software.

- Added MIT License
- Complete documentation suite (docs/, CLAUDE.md)
- 139 passing tests with PHP 8.4+ SQLite compatibility
- Semantic release workflow for automated versioning
- Contribution guidelines (CONTRIBUTING.md)
- Security notes for Docker isolation
- PayPal donation link
- License compliance documentation
