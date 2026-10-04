                 CORAL-STYLE ПОЭТАПНЫЙ ПОИСК (#tv-* для Tourvisor)
                 5 шагов: Откуда → Куда → Когда → Ночи → Туристы
                 =================================================== -->
            <div id="tour-search-section" class="tv-sc-shell th-coral-search th-coral-wizard th-wizard w-full" data-th-wizard="home" data-step="1" data-start-step="1">
                <?php
                if (!function_exists('th_departure_default_id')) {
                    require_once __DIR__ . '/../backend/config/departure_defaults.php';
                }
                $th_def_dep_name = htmlspecialchars(th_departure_default_name(), ENT_QUOTES, 'UTF-8');
                ?>

                <nav class="th-coral-wizard__rail" aria-label="Шаги поиска">
                    <button type="button" class="th-coral-wizard__rail-item is-active" data-thw-goto="1" aria-current="step">
                        <span class="th-coral-search__label">Откуда</span>
                        <span class="th-coral-wizard__rail-value" data-th-label="departure"><?php echo $th_def_dep_name; ?></span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="2">
                        <span class="th-coral-search__label">Куда</span>
                        <span class="th-coral-wizard__rail-value is-placeholder" data-th-label="country">Страна</span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="3">
                        <span class="th-coral-search__label">Когда</span>
                        <span class="th-coral-wizard__rail-value is-placeholder" data-th-label="dates">Даты</span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="4">
                        <span class="th-coral-search__label">Ночи</span>
                        <span class="th-coral-wizard__rail-value" data-th-label="nights">6–9 ночей</span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="5">
                        <span class="th-coral-search__label">Туристы</span>
                        <span class="th-coral-wizard__rail-value" data-th-label="tourists">2 взрослых</span>
                    </button>
                </nav>

                <div class="th-wizard__stepbar" aria-live="polite">
                    <div class="th-wizard__stepbar-track" aria-hidden="true">
                        <span class="th-wizard__stepbar-fill" data-thw-progress style="width:20%"></span>
                    </div>
                    <p class="th-wizard__stepbar-label" id="th-wizard-step-label">1 из 5 · Откуда</p>
                </div>

                <nav class="th-wizard__progress sr-only" aria-label="Шаги поиска">
                    <button type="button" class="th-wizard__dot is-active" data-thw-goto="1" aria-current="step"><span class="th-wizard__dot-num">1</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="2"><span class="th-wizard__dot-num">2</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="3"><span class="th-wizard__dot-num">3</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="4"><span class="th-wizard__dot-num">4</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="5"><span class="th-wizard__dot-num">5</span></button>
                </nav>

                <div class="th-wizard__panels">
                    <div class="th-wizard__panel is-active" data-panel="1">
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="departure" aria-label="Откуда">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Откуда</span>
                                <span class="th-coral-search__value" data-th-label="departure"><?php echo $th_def_dep_name; ?></span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back hidden>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="2" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="country" aria-label="Куда">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Куда</span>
                                <span class="th-coral-search__value is-placeholder" data-th-label="country">Выберите страну</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="3" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="dates" aria-label="Когда">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Когда</span>
                                <span class="th-coral-search__value is-placeholder" data-th-label="dates">Выберите даты</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="4" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="nights" aria-label="Сколько ночей">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Ночей</span>
                                <span class="th-coral-search__value" data-th-label="nights">6–9 ночей</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="5" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="tourists" aria-label="Туристы">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Туристы</span>
                                <span class="th-coral-search__value" data-th-label="tourists">2 взрослых</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button id="tv-search-btn" type="button" class="th-coral-search__search-btn button button-primary tv-sc-search-btn">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <span class="tv-sc-search-text">Найти</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="th-coral-wizard__filters-wrap">
                    <button type="button" class="th-coral-wizard__filters-btn" id="th-wizard-toggle-filters"
                            aria-expanded="false" aria-controls="th-wizard-filters-panel">
                        <i class="fas fa-sliders-h" aria-hidden="true"></i>
                        <span>Фильтры</span>
                    </button>
                    <div class="th-wizard__extra-filters" id="th-wizard-filters-panel" hidden aria-label="Дополнительные фильтры">
                        <div class="th-wizard__extra-filters-grid">
                            <div class="tv-sc-field tv-sc-field--sel th-wizard__filter-field">
                                <span class="tv-sc-field-label">Питание</span>
                                <button type="button" class="th-wizard__filter-trigger" data-th-filter-open="meal"
                                        aria-haspopup="dialog" aria-controls="th-search-meal-sheet">
                                    <span class="th-wizard__filter-value" data-th-filter-label="meal">Любое</span>
                                    <i class="fas fa-chevron-down th-wizard__filter-chevron" aria-hidden="true"></i>
                                </button>
                                <select id="tv-meal" class="tv-sc-select tv-select tv-filter-field th-wizard__filter-native" aria-label="Питание" tabindex="-1">
                                    <option value="">Любое</option>
                                </select>
                            </div>
                            <div class="tv-sc-field tv-sc-field--sel th-wizard__filter-field">
                                <span class="tv-sc-field-label">Курорт</span>
                                <button type="button" class="th-wizard__filter-trigger" data-th-filter-open="region"
                                        aria-haspopup="dialog" aria-controls="th-search-region-sheet">
                                    <span class="th-wizard__filter-value" data-th-filter-label="region">Любой</span>
                                    <i class="fas fa-chevron-down th-wizard__filter-chevron" aria-hidden="true"></i>
                                </button>
                                <select id="tv-region" class="tv-sc-select tv-select tv-filter-field th-wizard__filter-native" aria-label="Курорт" tabindex="-1">
                                    <option value="">Любой</option>
                                </select>
                            </div>
                            <div class="tv-sc-field tv-sc-field--sel th-wizard__filter-field">
                                <span class="tv-sc-field-label">Звёзды</span>
                                <button type="button" class="th-wizard__filter-trigger" data-th-filter-open="category"
                                        aria-haspopup="dialog" aria-controls="th-search-category-sheet">
                                    <span class="th-wizard__filter-value" data-th-filter-label="category">Любая</span>
                                    <i class="fas fa-chevron-down th-wizard__filter-chevron" aria-hidden="true"></i>
                                </button>
                                <select id="tv-category" class="tv-sc-select tv-select tv-filter-field th-wizard__filter-native" aria-label="Категория отеля" tabindex="-1">
                                    <option value="">Любая</option>
                                    <option value="3">3★+</option>
                                    <option value="4">4★+</option>
                                    <option value="5">5★</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" id="tv-filters-modal-open" class="sr-only" aria-hidden="true" tabindex="-1"
                                aria-haspopup="dialog" aria-controls="tv-filters-modal" aria-expanded="false">Фильтры</button>
                    </div>
                </div>

                <div class="th-coral-search__native" aria-hidden="true">
                    <select id="tv-departure" name="departureId" class="tv-select" tabindex="-1">
                        <option value="<?php echo (int) th_departure_default_id(); ?>"><?php echo $th_def_dep_name; ?></option>
                    </select>
                    <select id="tv-country" name="countryId" class="tv-select" tabindex="-1">
                        <option value="">Страна</option>
                    </select>
                    <div id="tv-sc-dates-field">
                        <button type="button" id="tv-sc-dates-btn" aria-haspopup="dialog" aria-expanded="false"
                                aria-controls="tv-sc-date-popup" tabindex="-1">
                            <span id="tv-sc-dates-display">Даты</span>
                        </button>
                        <div id="tv-dates-wrap" class="tv-sc-fp-hidden">
                            <input type="text" id="tv-dates" class="tv-search-control"
                                   placeholder="Выберите период" data-input readonly autocomplete="off" tabindex="-1">
                        </div>
                    </div>
                    <div id="tv-nights-trigger">
                        <button type="button" id="tv-nights-summary" tabindex="-1" aria-hidden="true">
                            <span id="tv-nights-summary-text">6–9 ночей</span>
                        </button>
                    </div>
                    <div id="tv-tourists-trigger">
                        <button type="button" id="tv-tourists-summary" tabindex="-1">
                            <span id="tv-tourists-summary-text">2 взрослых</span>
                        </button>
                    </div>
                </div>

                <div class="tv-sc-row th-wizard__legacy-fields" id="tv-sc-main-row" aria-hidden="true"></div>

                <!-- ─── ПОПАП: ТУРИСТЫ ─── -->
                <div id="tv-tourists-block" class="th-coral-popup th-coral-tourists-popup hidden" role="dialog" aria-label="Туристы" aria-modal="true">
                    <div class="th-coral-popup__backdrop" data-tv-tourists-close></div>
                    <div class="th-coral-popup__panel">
                        <div class="th-coral-popup__head">
                            <div class="th-coral-popup__head-main">
                                <span class="th-coral-popup__eyebrow">Travel Hub</span>
                                <span class="th-coral-popup__title">Туристы</span>
                            </div>
                            <button type="button" id="tv-tourists-close-btn" class="th-coral-popup__close" data-tv-tourists-close aria-label="Закрыть">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="th-coral-popup__body">
                    <div class="tv-sc-counter-row">
                        <span class="tv-sc-counter-label">Взрослые</span>
                        <div class="tv-sc-counter-ctrl">
                            <button type="button" id="tv-adults-minus" class="tv-sc-cnt-btn" aria-label="Меньше">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="tv-sc-cnt-val" id="tv-adults-value">2</span>
                            <button type="button" id="tv-adults-plus" class="tv-sc-cnt-btn" aria-label="Больше">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div id="tv-children-rows" class="tv-sc-children-rows"></div>
                    <button type="button" id="tv-add-child-btn" class="tv-sc-add-child">
                        <i class="fas fa-plus"></i> Добавить ребёнка
                    </button>
                    <div id="tv-child-age-picker" class="hidden tv-sc-age-picker">
                        <p class="tv-sc-age-hint">Возраст ребёнка</p>
                        <div id="tv-child-age-grid" class="tv-sc-age-grid"></div>
                    </div>
                    <label class="tv-sc-remember">
                        <input type="checkbox" id="tv-remember-tourists">
                        <span>Запомнить</span>
                    </label>
                        </div>
                    <button type="button" id="tv-tourists-apply" class="th-coral-popup__apply">
                        <i class="fas fa-check" aria-hidden="true"></i> Применить
                    </button>
                    </div>
                </div>

                <!-- ─── ПОПАП: ДАТЫ ─── -->
                <div id="tv-sc-date-popup" class="th-coral-popup th-coral-date-popup hidden"
                     role="dialog" aria-label="Когда вылетаете" aria-modal="true">
                    <div class="th-coral-popup__backdrop" data-sc-close="tv-sc-date-popup"></div>
                    <div class="th-coral-popup__panel">
                        <div class="th-coral-popup__head">
                            <div class="th-coral-popup__head-main">
                                <span class="th-coral-popup__eyebrow">Travel Hub</span>
                                <span class="th-coral-popup__title">Когда вылетаете?</span>
                            </div>
                            <button type="button" class="th-coral-popup__close" data-sc-close="tv-sc-date-popup" aria-label="Закрыть">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="th-coral-popup__body">
                            <p id="tv-sc-dates-preview" class="th-coral-popup__preview" aria-live="polite"></p>
                            <p id="tv-sc-dates-step" class="th-coral-popup__hint" aria-live="polite">Выберите период вылета (от и до)</p>
                            <div id="tv-sc-cal-panel" class="tv-sc-cal-panel">
                                <div id="tv-sc-cal-container" class="tv-sc-cal-container"></div>
                            </div>
                        </div>
                        <button type="button" id="tv-sc-dates-apply" class="th-coral-popup__apply">
                            <i class="fas fa-check" aria-hidden="true"></i> Применить
                        </button>
                    </div>
                </div>

                <div id="tv-sc-overlay" class="tv-sc-overlay" style="display:none" aria-hidden="true"></div>

            </div>
