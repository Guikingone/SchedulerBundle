# Changelog

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
