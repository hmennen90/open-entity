# License Compliance

This document describes the license compliance of OpenEntity and its dependencies.

## Project License

**OpenEntity is licensed under the MIT License.**

See [LICENSE](../LICENSE) for the full license text.

## License Compatibility

### MIT License Requirements

The MIT License is a permissive license that allows:
- Commercial use
- Modification
- Distribution
- Private use

Requirements:
- Include copyright notice
- Include license text

### Compatible Licenses

| License | Compatible with MIT | Notes |
|---------|---------------------|-------|
| MIT | ✅ Yes | Same license |
| BSD-2-Clause | ✅ Yes | Permissive |
| BSD-3-Clause | ✅ Yes | Permissive |
| Apache-2.0 | ✅ Yes | Permissive |
| ISC | ✅ Yes | Permissive |
| GPL-2.0/3.0 | ❌ No | Copyleft - would require project to become GPL |

### Important: GPL Compatibility

```
MIT → GPL:  ✅ Allowed (MIT code can be used in GPL projects)
GPL → MIT:  ❌ Not allowed (GPL code cannot be used in MIT projects)
```

If a dependency were **GPL-only**, OpenEntity would need to either:
1. Become GPL-licensed, or
2. Replace the dependency

## PHP Dependencies (Composer)

### License Summary

| License | Count | MIT-Compatible |
|---------|-------|----------------|
| MIT | ~70 | ✅ Yes |
| BSD-3-Clause | ~15 | ✅ Yes |
| Apache-2.0 | 1 | ✅ Yes |
| ISC | 1 | ✅ Yes |

### Multi-Licensed Packages

The following packages offer multiple license options:

#### nette/schema & nette/utils

```json
"license": ["BSD-3-Clause", "GPL-2.0-only", "GPL-3.0-only"]
```

**Resolution:** These packages are **multi-licensed** (OR, not AND). We choose **BSD-3-Clause**, which is compatible with MIT.

The array syntax `[...]` in composer.json means "choose one of these" (disjunction), not "all apply" (conjunction).

### Core Frameworks

| Framework | Package | License | Role |
|-----------|---------|---------|------|
| **Laravel** | laravel/framework | MIT | Backend PHP framework |
| **Vue.js** | vue | MIT | Frontend JavaScript framework |
| **TailwindCSS** | tailwindcss | MIT | Utility-first CSS framework |

### Other Key PHP Dependencies

| Package | License | Status |
|---------|---------|--------|
| guzzlehttp/guzzle | MIT | ✅ |
| symfony/* | MIT | ✅ |
| phpunit/phpunit | BSD-3-Clause | ✅ |
| mockery/mockery | BSD-3-Clause | ✅ |
| nette/schema | BSD-3-Clause (chosen) | ✅ |
| nette/utils | BSD-3-Clause (chosen) | ✅ |

## JavaScript Dependencies (NPM)

### Core Frameworks

| Framework | Package | License | Role |
|-----------|---------|---------|------|
| **Vue.js** | vue | MIT | Reactive UI framework |
| **Vue Router** | vue-router | MIT | Client-side routing |
| **Pinia** | pinia | MIT | State management |
| **TailwindCSS** | tailwindcss | MIT | Styling framework |

### Other Direct Dependencies

| Package | License | Status |
|---------|---------|--------|
| @headlessui/vue | MIT | ✅ |
| @heroicons/vue | MIT | ✅ |
| laravel-echo | MIT | ✅ |
| pusher-js | MIT | ✅ |

### Dev Dependencies

| Package | License | Status |
|---------|---------|--------|
| vite | MIT | ✅ |
| tailwindcss | MIT | ✅ |
| laravel-vite-plugin | MIT | ✅ |
| autoprefixer | MIT | ✅ |
| postcss | MIT | ✅ |

**All NPM dependencies are MIT licensed.**

## External Services & Tools

| Component | License | Notes |
|-----------|---------|-------|
| Ollama | MIT | Local LLM server |
| MySQL | GPL-2.0 | Used as service, not linked |
| Redis | BSD-3-Clause | Used as service |
| Nginx | BSD-2-Clause | Used as service |
| PHP | PHP License | Runtime environment |

**Note:** Using GPL-licensed software as a separate service (like MySQL) does not require your application to be GPL. The "copyleft" effect only applies when you link/include GPL code directly in your application.

## Docker Images

| Image | License | Notes |
|-------|---------|-------|
| php:fpm | PHP License | Base image |
| nginx:alpine | BSD-2-Clause | Web server |
| mysql:8.0 | GPL-2.0 | Database service |
| redis:alpine | BSD-3-Clause | Cache service |
| ollama/ollama | MIT | LLM service |

## Compliance Checklist

- [x] Project has MIT LICENSE file
- [x] All PHP dependencies are MIT-compatible
- [x] All NPM dependencies are MIT-compatible
- [x] No GPL-only dependencies in codebase
- [x] Multi-licensed packages have MIT-compatible option selected
- [x] External services don't affect project license
- [x] Copyright notices preserved in dependencies

## Verification Commands

### Check PHP Licenses

```bash
composer licenses
```

### Check for GPL in PHP Dependencies

```bash
composer licenses | grep -iE "GPL|LGPL|AGPL"
```

### Check NPM Licenses

```bash
npm ls --all | head -50
# or
npx license-checker --summary
```

## Acknowledgments

OpenEntity is built on the shoulders of giants. Special thanks to:

- **[Laravel](https://laravel.com/)** (MIT) - The elegant PHP framework that powers the backend
- **[Vue.js](https://vuejs.org/)** (MIT) - The progressive JavaScript framework for the frontend
- **[TailwindCSS](https://tailwindcss.com/)** (MIT) - The utility-first CSS framework
- **[Laravel Reverb](https://reverb.laravel.com/)** (MIT) - Real-time WebSocket server
- **[Ollama](https://ollama.com/)** (MIT) - Local large language model runner

## Conclusion

**OpenEntity is fully compliant with the MIT License.**

All dependencies are either:
1. MIT licensed
2. Licensed under MIT-compatible permissive licenses (BSD, Apache, ISC)
3. Multi-licensed with a MIT-compatible option available

No GPL-only or other copyleft-only dependencies exist in the project.

---

*Last verified: February 2026*
