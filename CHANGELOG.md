# Changelog

0.10.4
-----

### Build

##### Dependencies

* build(deps): symfony 7 support (#323) ([d2172a9](https://github.com/Guikingone/SchedulerBundle/commit/d2172a984c72590d71c1fec549fd1aebd5edee96))

---

0.10.3
-----

### Bug Fixes

##### Bridge

* Misnamed auth option in Redis transport factory (#312) ([a5b266](https://github.com/Guikingone/SchedulerBundle/commit/a5b26642f0d7787c7cf470585ad028c3439746b3))

##### Build

* Docker deps updated ([38ee77](https://github.com/Guikingone/SchedulerBundle/commit/38ee77f4c3993fdc62193081db11965df4bbcfb1))

##### Deps

* Symfony 6.3 deprecations removed ([647f3f](https://github.com/Guikingone/SchedulerBundle/commit/647f3fbe941683f5d611c9c2062dc7111a52837d))

##### Security

* Symfony/http-kernel updated ([379616](https://github.com/Guikingone/SchedulerBundle/commit/3796163f6a978481b28784cba3b54ae18bda9f1a))

---

0.10.2
-----

### Code Refactoring

##### Eventlistener

* StopWorkerOnNextTaskSubscriber improved (#306) ([be1729](https://github.com/Guikingone/SchedulerBundle/commit/be1729d44753dc4b310bffcb5f1356da3cba7a44))

##### Messenger

* Fix deprecations symfony 6.2 (#309) ([784587](https://github.com/Guikingone/SchedulerBundle/commit/784587fa8dcd2cbf1b774eb759c5685bc20468f7))

---

0.10.1
-----

### Builds

##### Deps

* Deps improved (#300) ([f7fca6](https://github.com/Guikingone/SchedulerBundle/commit/f7fca660aee9dd8366c472b25fa8ad9d9db50000))

### Documentation

##### Best practices

* best_practices.md updated (#301) ([005cc3](https://github.com/Guikingone/SchedulerBundle/commit/005cc3bf9edf6a6fc4f84d0b3fb02528bd344df0))

---

0.10.0
-----

### Features

##### Command

* StopWorkerCommand introduced (#296) ([f07a8a](https://github.com/Guikingone/SchedulerBundle/commit/f07a8afe0c8e9a53c35aa02942befd070e8c2ace))

### Code Refactoring

##### Scheduler

* Reboot handling improved (#298) ([b3e493](https://github.com/Guikingone/SchedulerBundle/commit/b3e4939aabdd80366d38a156d13eab7b3d930ce1))

### Builds

##### Composer

* Infection extension support added (#293) ([7ae19f](https://github.com/Guikingone/SchedulerBundle/commit/7ae19f53ec5a915c075c1274cb27f744ea9542bc))

##### Docker

* Pin apt versions for Dockerfile (#291) ([dd3972](https://github.com/Guikingone/SchedulerBundle/commit/dd39721e4bc6f16a5c83e81122392638fefe4c6a))

---

0.9.4
-----

### Bug Fixes

##### Bridge

* Exception thrown during task creation (#287) ([25d9da](https://github.com/Guikingone/SchedulerBundle/commit/25d9dad67c1181f3cfbef36010370ceeac1bfc89))

### Code Refactoring

##### Bridge

* Connection improved (#282) ([ee9979](https://github.com/Guikingone/SchedulerBundle/commit/ee9979154763738bf86e87e4377d959a93a39d31))

##### Build

* Improve .gitattributes for composer install (#270) ([a8335c](https://github.com/Guikingone/SchedulerBundle/commit/a8335cb72e8de96f33512ef352465d622bbd1cb5))

##### Command

* Attribute introduced (#281) ([a7326f](https://github.com/Guikingone/SchedulerBundle/commit/a7326f1e9211c0db8bb3f054dd9dd320f8e90a51))

##### Core

* Improvement on command display && normalizer condition (#277) ([d64656](https://github.com/Guikingone/SchedulerBundle/commit/d646564a749be8f2de5f878072773eff8543e6d2))
* Scheduler scheduling phase improved (#286) ([4b7905](https://github.com/Guikingone/SchedulerBundle/commit/4b7905ef32d4a27a7b8768169a8e811c69dec6c8))

##### Serializer

* Normalizers deprecations removed (#266) ([cd23d8](https://github.com/Guikingone/SchedulerBundle/commit/cd23d898a630abc8815c273fed34b388032e66e3))

### Builds

##### Deps

* Dev depdencies updated (#262) ([fb98d6](https://github.com/Guikingone/SchedulerBundle/commit/fb98d6f82a19d1e3db503a21a8a173ab53d11c5b))

##### Security

* Replace snyk with symfonycorp/security-checker-action (#267) ([1a2a55](https://github.com/Guikingone/SchedulerBundle/commit/1a2a55f155b90ca69b3a319df700f650ed3b95fa))

### Continuous Integrations

##### Docker

* Fix hadolint workflow (#268) ([fc95c8](https://github.com/Guikingone/SchedulerBundle/commit/fc95c859483d9e2d02888ba9d11bfd68d5b94e73))

##### Github

* Simplify workflows (#271) ([19dbac](https://github.com/Guikingone/SchedulerBundle/commit/19dbac3b5c23cac282ca53f52d13e156ce3870e4))

##### Tools

* MacOS dropped for rector & static analysis workflows (#269) ([5427b3](https://github.com/Guikingone/SchedulerBundle/commit/5427b3b160abba4892422bc9ecbbcb8e847c505f))

---

0.9.3
-----
### Features

##### Command

* Suggestions implemented (#256) ([d4f0b0](https://github.com/Guikingone/SchedulerBundle/commit/d4f0b05aaa59f93aece6d5f71ad7f4b7fcc8d036))

### Code Refactoring

##### Core

* Improvements on serializer usage (#261) ([15db6c](https://github.com/Guikingone/SchedulerBundle/commit/15db6c8e7923eb37cd24a9d88604e1dd47a2c07d))

##### Serializer

* Support for TraceableNormalizer && TraceableDenormalizer added (#260) ([15dbc8](https://github.com/Guikingone/SchedulerBundle/commit/15dbc87f44dff462dd277c6c67ef1eac74439356))

---

0.9.2
-----
### Bug Fixes

##### Tests

* Improvements (#247) ([8af5db](https://github.com/Guikingone/SchedulerBundle/commit/8af5dbe6b7614182f86768f35add2550734e758a))

### Code Refactoring

##### Core

* Static analysis improved (#244) ([96d157](https://github.com/Guikingone/SchedulerBundle/commit/96d1579192f79493a4a41638d998598bf1f894de))
* Static analysis improved + named arguments improved (#246) ([a602fb](https://github.com/Guikingone/SchedulerBundle/commit/a602fba758fe455be541d9244ff128c568577b74))

### Builds

##### Rector

* Version fixed (#250) ([026602](https://github.com/Guikingone/SchedulerBundle/commit/0266026e284939b388a9e41a214e7b2cfeb27a9a))

---

0.9.1
-----

### Code Refactoring

##### Dic

* Fix on scheduler injection (#242) ([4fd50b](https://github.com/Guikingone/SchedulerBundle/commit/4fd50b1bee10c228c86e0f871cbd786441e105d2))

---

0.9.0
-----

### Features

##### Core

* Fibers support (#235) ([de0f3b](https://github.com/Guikingone/SchedulerBundle/commit/de0f3b496a25718de25f26270ed91570f76427cb))

##### Expression

* Add ExactExpressionBuilder (#224) ([865265](https://github.com/Guikingone/SchedulerBundle/commit/86526518b59b40bf7b5db183f29ce8528ba3b645))

##### Tasks

* Add option deleteAfterExecute (#227) ([af1b2d](https://github.com/Guikingone/SchedulerBundle/commit/af1b2dfa230c148ae67ef1c5049f812dda2b33df))

### Code Refactoring

##### Bridge

* Postgres support improved (#233) ([7dfa93](https://github.com/Guikingone/SchedulerBundle/commit/7dfa93f198a4f17b973ed74e177ef53ac4a5df1d))

##### Core

* Fibers improvement (#238) ([62419c](https://github.com/Guikingone/SchedulerBundle/commit/62419c7a99e994e053245d7714a32ed24430a857))
* Static analysis fixed (#239) ([20d737](https://github.com/Guikingone/SchedulerBundle/commit/20d7373a79b05a8ec5b232a6e47ff30cc89dc264))
* Transport configuration support (#202) ([e58fc1](https://github.com/Guikingone/SchedulerBundle/commit/e58fc15fa27cd943bc71a25961b82f1a69e75e0a))

##### Dic

* SchedulerAwareInterface started (#221) ([059c74](https://github.com/Guikingone/SchedulerBundle/commit/059c74276e2616625ec4db1e1142c599cf034cdd))

##### Worker

* ExecutionPolicy introduced (#237) ([da8111](https://github.com/Guikingone/SchedulerBundle/commit/da8111b56bf8ad040578f78ae75ae4bb7cc1c88e))

### Continuous Integrations

##### Php

* 8.2 support started (#236) ([81a4ac](https://github.com/Guikingone/SchedulerBundle/commit/81a4ac3a3aa134d81ca66d5e76e594ee8c6c8d80))

---

0.8.1
-----

### Code Refactoring

##### Scheduler

* Synchronization improved (#220) ([4a675b6](https://github.com/Guikingone/SchedulerBundle/commit/4a675b6e5d1f41d2d9f2c67a2f808a899e441311))

---

0.8.0
-----

### Features

##### Middleware

* Registry introduced (#203) ([06f36c](https://github.com/Guikingone/SchedulerBundle/commit/06f36c9d18ab407b707ca8a128102604d29b5787))

##### Transport

* Registry introduced (#212) ([fe730b](https://github.com/Guikingone/SchedulerBundle/commit/fe730b463dc8da89abb08788b6f06dd9488ad197))

### Builds

##### Docker

* Configuration started (#204) ([baea89](https://github.com/Guikingone/SchedulerBundle/commit/baea89a8d9134c4d9c2f45212d4728f51d00821a))

##### Php

* 8.1 added && Symfony 6.0 (#31) ([dcbc6f](https://github.com/Guikingone/SchedulerBundle/commit/dcbc6f731156daa2650e399288b569b10acffbb6))

---

0.7.1
-----

### Builds

##### Psr

* Update (#196) ([ac22f6](https://github.com/Guikingone/SchedulerBundle/commit/ac22f642df2b950ae1c7ac651afd6c6a3e478218))

---

0.7.0
-----

### Features

##### Core

* Task sort improved (#183) ([92eb0e](https://github.com/Guikingone/SchedulerBundle/commit/92eb0e4269b3dc7292ade221f94f5e0bae69e1c7))

##### DIC

* Configuration improved (#184) ([2ac8b4](https://github.com/Guikingone/SchedulerBundle/commit/2ac8b4c9919aa0d26e916203999f70d314b7bad5))

##### Scheduler

* Task execution preemption (#145) ([61e887](https://github.com/Guikingone/SchedulerBundle/commit/61e887e43d6c20af1546caea9a29c3da2880fa33))

### Code Refactoring

##### Worker

* Improvements on stop (#186) ([c751c8](https://github.com/Guikingone/SchedulerBundle/commit/c751c8613d61646b12f579c9c6c70c5a2acdbc7e))
* WorkerConfiguration introduced (#187) ([a84878](https://github.com/Guikingone/SchedulerBundle/commit/a848788696330d670d35acc4bb6b9a7e4b485578))

---

0.6.2
-----

### Code Refactoring

##### Scheduler

* Strict mode (#180) ([6ea8bb](https://github.com/Guikingone/SchedulerBundle/commit/6ea8bb5998897097e4ee603190a2f102dd4d7eea))

##### Worker

* Pause task execution using signal (#177) ([75558a](https://github.com/Guikingone/SchedulerBundle/commit/75558a7bf7d0781c5c74569de87849661cea6882))

---

0.6.1
-----
### Code Refactoring

##### Worker

* Stop sleeping worker (#173) ([9bd97d](https://github.com/Guikingone/SchedulerBundle/commit/9bd97dd840a82997434ba87b7fa429998ede000c))
* `dbal` dsn support added for `DoctrineTransportFactory` (#173) ([9bd97d](https://github.com/Guikingone/SchedulerBundle/commit/9bd97dd840a82997434ba87b7fa429998ede000c))

---

0.6.0
-----
### Code Refactoring

### ⚠ BREAKING CHANGES

##### Worker

* Long running tasks + parallel process lock (#102) ([e54e8a](https://github.com/Guikingone/SchedulerBundle/commit/e54e8a20e07aba561a20e5d750ff9ac744c11095))

---

# Changelog

0.5.5
-----

### Code Refactoring

##### Bridge

* Doctrine connection improved (#166) ([145fbf](https://github.com/Guikingone/SchedulerBundle/commit/145fbfb5334d464f9efd19f67bf9fed11fe35f70))

### Bug Fixes

##### Command

* scheduler:consume --wait (#160) ([145fbf](https://github.com/Guikingone/SchedulerBundle/commit/795067ae212739291edf6f34416429050fcb4f71))

---

0.5.4
-----

### Features

##### Command

* --force option added to scheduler:consume (#161) ([81b23a](https://github.com/Guikingone/SchedulerBundle/commit/81b23a790a7e0f87e25011a4895fa427ff5cd1cc))

### Code Refactoring

##### DIC

* MessengerTaskRunner injection fixed (#163) ([a27a85](https://github.com/Guikingone/SchedulerBundle/commit/a27a8515f60e03d5a76165be868f95f2578886f7))

---

0.5.3
-----

### Bug Fixes

##### Kernel

* SchedulerCacheClearer improved (#153) ([15b746](https://github.com/Guikingone/SchedulerBundle/commit/15b74620b689fb167ff61f78555ee4516c4178d2))

---

0.5.2
-----

### Code Refactoring

##### Transport

* FilesystemTransportFactory improved (#149) ([35632d](https://github.com/Guikingone/SchedulerBundle/commit/35632d8ed23c529595f88572472d29be67f3ff76))

---

0.5.1
-----

### Builds

##### Composer

* Constraints (#146) ([31861d](https://github.com/Guikingone/SchedulerBundle/commit/31861d3359866a8974cf59bb385e2ebbc1c94a06))

---

0.5.0
-----

### ⚠ BREAKING CHANGES

##### Worker|task|runner|transport

* Worker has been refactored & runners API has been changed ([a4bf18](https://github.com/Guikingone/SchedulerBundle/commit/a4bf18c847f827194009148d81dc10cef6882551))

### Features

##### Command

* ExecuteTaskCommand introduced (#72) ([866e6c](https://github.com/Guikingone/SchedulerBundle/commit/866e6c893cf175b132aa5a412e4394a7d9392835))
* ListTaskCommand - display substasks (#127) ([a21379](https://github.com/Guikingone/SchedulerBundle/commit/a21379fa4bbb6d9e40c03ad6b51506720890b473))

##### Core

* Mercure support (#132) ([1072fd](https://github.com/Guikingone/SchedulerBundle/commit/1072fdba8d8b7e97a5c28ea4d4840a10d4df4196))
* Probe (#32) ([08c8fa](https://github.com/Guikingone/SchedulerBundle/commit/08c8fa0265832fb09410dbfbfb636c083a8fe5f5))
* Lazy loading ([a4bf18](https://github.com/Guikingone/SchedulerBundle/commit/a4bf18c847f827194009148d81dc10cef6882551))

### Code Refactoring

##### Command

* ExecuteTaskCommand - name option shortcut removed (#136) ([4f17ce](https://github.com/Guikingone/SchedulerBundle/commit/4f17ce69188358e01e88bd323b5699ca22733e1a))

##### Serializer

* Custom DateTimeNormalizer FORMAT_KEY to store microseconds (#123) ([0256b2](https://github.com/Guikingone/SchedulerBundle/commit/0256b2d08b0b4105465ad31a0adb596f6b5184a1))

##### Worker

* Fork improved (#137) ([d067a3](https://github.com/Guikingone/SchedulerBundle/commit/d067a3003d014ad7518b7f896e276e788c785de9))

##### Worker|runner

* Improvements (#103) ([1f6d4b](https://github.com/Guikingone/SchedulerBundle/commit/1f6d4b5043a93e2064ecc17fe28670f9cff4948b))

##### Worker|task|runner|transport

* Chained tasks handling + LazyTaskList introduced (#126) ([a4bf18](https://github.com/Guikingone/SchedulerBundle/commit/a4bf18c847f827194009148d81dc10cef6882551))

### Builds

##### Composer

* Symfony 5.3 support (#130) ([eba50a](https://github.com/Guikingone/SchedulerBundle/commit/eba50a92cb7f1eb66d03cd8e8524442e974fba1b))

---

0.4.9
-----

# Description

* BatchPolicy sort improved (see https://github.com/Guikingone/SchedulerBundle/pull/120) - Thanks @jvancoillie

### Extra

- The whole improvements tagged in `0.4.8` are also released via this release.

### API

No BC Breaks introduced via this release.

---

0.4.8
-----

* `Scheduler::getDueTasks()` fixed (see https://github.com/Guikingone/SchedulerBundle/pull/117)
* Tools improved (see https://github.com/Guikingone/SchedulerBundle/pull/99)

### API

No BC Breaks introduced via this release.

---

0.4.7
-----

* Due tasks filter fixed (see https://github.com/Guikingone/SchedulerBundle/pull/114)

### API

No BC Breaks introduced via this release.

---

0.4.6
-----

* Next execution allowed date fixed (see https://github.com/Guikingone/SchedulerBundle/pull/112)

### API

No BC Breaks introduced via this release.

---

0.4.5
-----

* Due tasks filter improved (see https://github.com/Guikingone/SchedulerBundle/pull/107)

### API

No BC Breaks introduced via this release.

---

0.4.4
-----

* Doctrine bridge sort fixed (see https://github.com/Guikingone/SchedulerBundle/pull/95)
* Task lock management improved (see https://github.com/Guikingone/SchedulerBundle/pull/91)
* Chained sub-tasks sort (see https://github.com/Guikingone/SchedulerBundle/pull/89) - Thanks @jvancoillie
* Tests improvements on Doctrine bridge (see https://github.com/Guikingone/SchedulerBundle/pull/66)
* Tools dependencies upgraded (see https://github.com/Guikingone/SchedulerBundle/pull/97) - Thanks @jmsche
* CI improvements (see https://github.com/Guikingone/SchedulerBundle/pull/98) - Thanks @jmsche
* README.md improved (see https://github.com/Guikingone/SchedulerBundle/pull/100) - Thanks @jmsche

### API

No BC Breaks introduced via this release.

---

0.4.3
-----

* FIFO sort fixed (see https://github.com/Guikingone/SchedulerBundle/pull/75)
* DeadlinePolicy sort improved (see https://github.com/Guikingone/SchedulerBundle/pull/80)
* Doctrine connection extra logger call removed (see https://github.com/Guikingone/SchedulerBundle/pull/81)
* Configuration set for reference dump (see https://github.com/Guikingone/SchedulerBundle/pull/83) - Thanks @jvancoillie

### API

No BC Breaks introduced via this release.

---

0.4.2
-----

* Fluent expression fixed (see https://github.com/Guikingone/SchedulerBundle/pull/62) - Thanks @babeuloula
* Worker improvements (see https://github.com/Guikingone/SchedulerBundle/pull/61)

### API

No BC Breaks introduced via this release.

---

0.4.1
-----

* Extension fixed on transport access (see https://github.com/Guikingone/SchedulerBundle/pull/57)

### API

No BC Breaks introduced via this release.

---

0.4.0
-----

* Doctrine lock & worker single_run fix (see https://github.com/Guikingone/SchedulerBundle/pull/46)
* Snyk analyze added (see https://github.com/Guikingone/SchedulerBundle/pull/48)
* Doctrine auto_setup fix (see https://github.com/Guikingone/SchedulerBundle/pull/43)
* Cache transport added (see https://github.com/Guikingone/SchedulerBundle/pull/16)
* Documentation improvements (see https://github.com/Guikingone/SchedulerBundle/pull/16)
* Core improvements (see https://github.com/Guikingone/SchedulerBundle/pull/16)
* Core improvements (see https://github.com/Guikingone/SchedulerBundle/pull/52)

---

0.3.4
-----

* Composer dependencies improvement (see https://github.com/Guikingone/SchedulerBundle/pull/37)
* Infection configuration improved (see https://github.com/Guikingone/SchedulerBundle/pull/38)
* Rector upgraded (see https://github.com/Guikingone/SchedulerBundle/pull/39)
* fix(bridge): Doctrine configuration fixed (see https://github.com/Guikingone/SchedulerBundle/pull/42)

---

0.3.3
-----

* Doctrine bridge improvements (see https://github.com/Guikingone/SchedulerBundle/pull/36)

---

0.3.2
-----

* Compound factories fixed (see https://github.com/Guikingone/SchedulerBundle/pull/34)

---

0.3.1
-----

* Scheduler definitions fix (see https://github.com/Guikingone/SchedulerBundle/pull/29)
* Expression builders definition fix (see https://github.com/Guikingone/SchedulerBundle/pull/30)

---

0.3.0
-----

* Doctrine dependencies updated (see https://github.com/Guikingone/SchedulerBundle/pull/17)
* Middleware introduced (see https://github.com/Guikingone/SchedulerBundle/pull/19)
* `Scheduler::yieldTask()` introduced (see https://github.com/Guikingone/SchedulerBundle/pull/19)
* `scheduler:yield` command added (see https://github.com/Guikingone/SchedulerBundle/pull/19)
* Documentation improvements (see https://github.com/Guikingone/SchedulerBundle/pull/19)
* "Fluent" expressions (see https://github.com/Guikingone/SchedulerBundle/pull/26)
* Core improvements (https://github.com/Guikingone/SchedulerBundle/pull/27)

---

0.2.0
-----

* Task notifications added (see https://github.com/Guikingone/SchedulerBundle/pull/1)
* Task & Worker lifecycle logs added (see https://github.com/Guikingone/SchedulerBundle/pull/5)
* PHP 7.2 & 7.3 support dropped (see https://github.com/Guikingone/SchedulerBundle/pull/13)
* PHP 8.0 support added (see https://github.com/Guikingone/SchedulerBundle/pull/13)

---

0.1.0
-----

* Introduced the bundle
