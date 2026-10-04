            <!-- Результаты поиска -->
            <section id="tv-results-section" class="bg-[#F9FAFB] py-4 md:py-6 border-t border-gray-200/60">
            <div class="th-container mx-auto px-4 sm:px-6 md:px-8 max-w-7xl">
            <div id="tv-results-wrapper" class="tv-results-shell hidden">
                <div class="tv-results-lead-bar hidden" id="tv-results-lead-bar">
                    <button type="button" class="tv-results-lead-bar__btn" data-open-lead-modal="results-toolbar">
                        <i class="fas fa-phone" aria-hidden="true"></i>
                        Не нашли идеальный тур? Оставьте телефон — подберём
                    </button>
                </div>
                <div id="tv-search-alt-banner" class="tv-search-alt-banner hidden" role="status"></div>
                <!-- Двухколоночный макет: sidebar слева + карточки справа -->
                <div class="tv-results-layout">
                    <!-- Sidebar с фильтрами (только на десктопе) -->
                    <aside class="tv-results-sidebar tv-post-filters" id="tv-results-sidebar" aria-label="Фильтры результатов">
                        <div class="tv-results-sidebar__title">
                            <i class="fas fa-sliders-h"></i>
                            Уточнить выдачу
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label th-filter-stars-label">Звёздность</span>
                            <div class="tv-pf-chips">
                                <button type="button" class="tv-pf-chip" data-pf-star data-pf-value="5" aria-pressed="false">5 звёзд</button>
                                <button type="button" class="tv-pf-chip" data-pf-star data-pf-value="4" aria-pressed="false">4 звезды</button>
                                <button type="button" class="tv-pf-chip" data-pf-star data-pf-value="3plus" aria-pressed="false">3 и выше</button>
                            </div>
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label">Питание</span>
                            <div class="tv-pf-chips" data-pf-meals>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="AI" aria-pressed="false">Всё включено</button>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="HB" aria-pressed="false">Завтрак + ужин</button>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="BB" aria-pressed="false">Завтрак</button>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="RO" aria-pressed="false">Без питания</button>
                            </div>
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label">Бюджет, ₽</span>
                            <div class="tv-pf-budget-row">
                                <input type="number" data-pf-price-min class="tv-filter-field" inputmode="numeric" min="0" step="1000" placeholder="От" autocomplete="off">
                                <span aria-hidden="true">—</span>
                                <input type="number" data-pf-price-max class="tv-filter-field" inputmode="numeric" min="0" step="1000" placeholder="До" autocomplete="off">
                            </div>
                            <div class="tv-pf-chips tv-pf-chips--budget">
                                <button type="button" class="tv-pf-chip tv-pf-chip--sm" data-pf-budget-quick="150000">до 150 тыс.</button>
                                <button type="button" class="tv-pf-chip tv-pf-chip--sm" data-pf-budget-quick="200000">до 200 тыс.</button>
                                <button type="button" class="tv-pf-chip tv-pf-chip--sm" data-pf-budget-quick="300000">до 300 тыс.</button>
                            </div>
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label">Курорты</span>
                            <div class="tv-pf-regions" data-pf-regions><p class="tv-pf-hint">Появятся после поиска</p></div>
                        </div>
                        <div class="tv-sidebar-filter-group" data-pf-beach-group style="display:none">
                            <span class="tv-sidebar-filter-label">Линия пляжа</span>
                            <div class="tv-pf-chips">
                                <button type="button" class="tv-pf-chip" data-pf-beach="1" aria-pressed="false">1-я линия (у моря)</button>
                                <button type="button" class="tv-pf-chip" data-pf-beach="2" aria-pressed="false">2-я линия</button>
                            </div>
                        </div>
                        <button type="button" data-pf-reset class="tv-pf-reset">Сбросить фильтры</button>
                    </aside>

                    <!-- Основная колонка: прогресс + карточки -->
                    <div class="tv-results-main" style="min-width:0">
                        <div class="tv-results-toolbar">
                            <h3 class="tv-results-toolbar__title heading-font text-xl font-bold text-slate-900">
                                Найдено <span id="tv-result-count">0</span> туров
                            </h3>
                            <div class="tv-sort-rail">
                                <select id="tv-sort" class="tv-select tv-sort-select px-3 py-2 rounded-xl border border-slate-200 text-slate-700">
                                    <option value="price-asc">Сначала дешевые</option>
                                    <option value="price-desc">Сначала дорогие</option>
                                    <option value="rating">По рейтингу</option>
                                </select>
                            </div>
                        </div>
                        <div id="tv-search-progress" class="hidden mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-3">
                                <div class="animate-spin w-5 h-5 border-2 border-[#FF6B6B] border-t-transparent rounded-full"></div>
                                <span class="text-slate-600">Поиск туров...</span>
                                <span id="tv-progress-text" class="text-slate-500 text-sm"></span>
                            </div>
                        </div>
                        <div id="tv-search-results" class="tv-search-results-grid th-tour-grid">
                            <!-- Карточки туров подставляются JS -->
                        </div>
                        <div id="tv-load-more-wrapper" class="mt-10 text-center hidden">
                            <button type="button" id="tv-load-more-btn" class="button button-primary px-8 py-3.5 text-sm disabled:opacity-70 disabled:pointer-events-none disabled:hover:scale-100">
                                <i class="fas fa-plus-circle mr-2"></i><span id="tv-load-more-text">Загрузить ещё туры</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
