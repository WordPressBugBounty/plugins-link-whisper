<style>
    /* ===== Review Modal Overlay ===== */
.wpil-review-modal{
  position: fixed;
  inset: 0;
  z-index: 999999;
}
.wpil-review-modal.hidden{ display:none; }

.wpil-review-modal__backdrop{
  position:absolute;
  inset:0;
  background: rgba(15,23,42,.45);
  backdrop-filter: blur(2px);
}
.wpil-review-modal__panel{
  position:relative;
  z-index:1;
  width: min(1040px, calc(100vw - 40px));
  max-height: calc(100vh - 40px);
  margin: 20px auto;
  background:#fff;
  border-radius: 16px;
  box-shadow: 0 20px 50px rgba(0,0,0,.25);
  overflow:hidden;
  display:flex;
  flex-direction:column;
}
.wpil-review-modal__header{
  padding: 16px 18px;
  border-bottom: 1px solid #e5e7eb;
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
}
.wpil-review-modal__close{
  appearance:none;
  border:0;
  background:transparent;
  font-size: 24px;
  line-height: 1;
  cursor:pointer;
  color:#64748b;
}
.wpil-review-modal__close:hover{ color:#0f172a; }

.wpil-review-modal__body{
  padding: 18px;
  overflow:auto;
}
.wpil-review-modal__footer{
  padding: 14px 18px;
  border-top: 1px solid #e5e7eb;
  background:#fff;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 12px;
}
.anchor-highlight{
  color:#7F5AF0;
  font-weight:700;
  border-bottom:2px solid #E9D5FF;
  padding-bottom:1px;
}

.wpil-review-field-list{
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:10px;
}
.wpil-review-sort-list{
  display:flex;
  align-items:center;
  gap:8px;
}
.wpil-review-sort-menu{
  position:relative;
}
.wpil-review-sort-toggle{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:6px 12px;
  border:1px solid #e5e7eb;
  border-radius:10px;
  background:#fff;
  font-size:12px;
  font-weight:600;
  color:#475569;
  cursor:pointer;
  user-select:none;
  transition:all .15s ease;
}
.wpil-review-sort-toggle.is-open{
  border-color:#7F5AF0;
  color:#4c1d95;
  box-shadow:0 6px 14px rgba(127,90,240,.12);
}
.wpil-review-sort-menu-panel{
  position:absolute;
  top:calc(100% + 6px);
  left:0;
  z-index:6;
  width:220px;
  max-height:240px;
  overflow:auto;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:12px;
  box-shadow:0 16px 30px rgba(15,23,42,.12);
  padding:8px;
  display:none;
}
.wpil-review-sort-menu-panel.is-open{
  display:block;
}
.wpil-review-sort-option{
  width:100%;
  text-align:left;
  border:0;
  background:transparent;
  padding:6px 8px;
  border-radius:8px;
  font-size:12px;
  font-weight:600;
  color:#475569;
  cursor:pointer;
}
.wpil-review-sort-option:hover{
  background:#f8fafc;
}
.wpil-review-sort-option.is-active{
  color:#4c1d95;
  background:#f5f3ff;
}
.wpil-review-field-toggle{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:6px 12px;
  border:1px solid #e5e7eb;
  border-radius:10px;
  background:#fff;
  font-size:12px;
  font-weight:600;
  color:#475569;
  cursor:pointer;
  user-select:none;
  transition:all .15s ease;
}
.wpil-review-field-toggle.is-open{
  border-color:#7F5AF0;
  color:#4c1d95;
  box-shadow:0 6px 14px rgba(127,90,240,.12);
}
.wpil-review-field-menu{
  position:relative;
}
.wpil-review-field-menu-panel{
  position:absolute;
  top:calc(100% + 6px);
  left:0;
  z-index:5;
  width:260px;
  max-height:240px;
  overflow:auto;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:12px;
  box-shadow:0 16px 30px rgba(15,23,42,.12);
  padding:8px;
  display:none;
}
.wpil-review-field-menu-panel.is-open{
  display:block;
}
.wpil-review-field{
  display:flex;
  align-items:center;
  gap:8px;
  padding:6px 8px;
  border-radius:8px;
  font-size:12px;
  font-weight:600;
  color:#475569;
  cursor:pointer;
  user-select:none;
}
.wpil-review-field:hover{
  background:#f8fafc;
}
.wpil-review-field input{
  width:14px;
  height:14px;
  accent-color:#7F5AF0;
}
.wpil-review-meta{
  margin-top:8px;
  font-size:12px;
  color:#475569;
  display:flex;
  flex-direction:column;
  gap:4px;
}
.wpil-review-meta b{
  color:#0f172a;
  font-weight:700;
}
.wpil-review-meta a{
  color:#4f46e5;
  text-decoration:none;
}
.wpil-review-meta a:hover{
  text-decoration:underline;
}
.wpil-review-meta > div{
  display:flex;
  gap:4px;
  align-items:center;
}

/* lock scroll while modal open */
body.wpil-review-open{ overflow:hidden; }

</style>
<script>
    jQuery(function($){
        var POLL_MS = 6000;
        var MAX_VISIBLE_ITEMS = 5;

        var ajaxUrl = (window.wpilReview && wpilReview.ajax_url) ? wpilReview.ajax_url : (window.ajaxurl || '');
        var nonce   = (window.wpilReview && wpilReview.nonce) ? wpilReview.nonce : '';
        function getReviewFixType(){
            var type = ($('#wpil-review-fix-type').val() || '').toString();
            if(!type && window.wpilReview && window.wpilReview.fix_type){
                type = String(window.wpilReview.fix_type);
            }
            return type;
        }

        function getReviewProcessKey(){
            var key = ($('#wpil-review-process-key').val() || '').toString();
            if(!key && window.wpilReview && window.wpilReview.process_key){
                key = String(window.wpilReview.process_key);
            }
            if((!key || key === 'all')){
                var fixType = getReviewFixType();
                if(fixType && window.WPIL_DASHBOARD_PROCESS_KEYS && window.WPIL_DASHBOARD_PROCESS_KEYS[fixType]){
                    key = String(window.WPIL_DASHBOARD_PROCESS_KEYS[fixType]);
                }
            }
            return key;
        }

        // Existing controls in scanning template
        var $linkMode = $('#wpil-link-mode');
        var $reviewButton = $('#wpil-review-open');
        var $reviewSection = $('#wpil-manual-review-section');

        // Modal elements
        var $modal     = $('#wpil-review-modal');
        var $list      = $('#wpil-review-list');
        var $empty     = $('#wpil-review-empty-state');
        var $remaining = $modal.find('[data-role="remaining"]');
        var $selected  = $modal.find('[data-role="selected"]');
        var $visible   = $modal.find('[data-role="visible"]');
        var $emptyText = $('#wpil-review-empty-text');
        var $reviewReadyCount = $('[data-role="review-ready-count"]');
        var $reviewButton = $('#wpil-review-open');
        var $reviewButtonLabel = $reviewButton.find('[data-role="review-button-label"]');
        var $reviewButtonSpinner = $reviewButton.find('[data-role="review-button-spinner"]');
        var finishedLooking = null;
        var remainingCount = null;
        var hasEverLoaded = false;

        var itemsById = {};
        var suppressedSentenceKeys = {};
        var suppressedPairKeys = {};
        var pendingDecisionIds = {};
        var pollTimer = null;
        var isPolling = false;
        var suggestionRefreshTimer = null;
        var reviewCountTimer = null;
        var reviewCountPollingPaused = false;

        var fieldOptions = [
            { key: 'ai', label: 'AI Relatedness', default: true },
            { key: 'type', label: 'Type', default: false },
            { key: 'tax', label: 'Categories/Tags', default: false },
            { key: 'inbound', label: 'Inbound Internal', default: false },
            { key: 'outbound_internal', label: 'Outbound Internal', default: false },
            { key: 'outbound_external', label: 'Outbound External', default: false },
            { key: 'post_id', label: 'Post ID', default: false },
            { key: 'language', label: 'Language Code', default: false },
            { key: 'view_link', label: 'View Link', default: false }
        ];
        var fieldStorageKey = 'wpilReviewFields';
        var sortStorageKey = 'wpilReviewSort';
        var currentSortKey = 'ai';
        var currentSortDir = 'desc';

        var numericSortKeys = {
            ai: true,
            inbound: true,
            outbound_internal: true,
            outbound_external: true,
            post_id: true
        };

        function escapeHtml(str){
            return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        function escapeAttr(str){
            return escapeHtml(str);
        }

        function sanitizeProposedHtml(html){
            var $wrap = $('<div>').html(html || '');
            $wrap.find('script,style').remove();

            $wrap.find('*').each(function(){
            var tag = this.tagName.toLowerCase();
            if(tag !== 'a'){
                $(this).replaceWith($(this).text());
                return;
            }

            var $a = $(this);
            var href  = $a.attr('href') || '';
            var title = $a.attr('title') || '';

            $.each(this.attributes, function(){ $a.removeAttr(this.name); });

            if(href)  $a.attr('href', href);
            if(title) $a.attr('title', title);

            $a.attr('target','_blank').attr('rel','noopener');
            $a.addClass('anchor-highlight'); // your nice highlight
            });

            return $wrap.html();
        }

        function openModal(){
            remainingCount = null;
            finishedLooking = false;
            hasEverLoaded = false;
            pendingDecisionIds = {};
            setEmptyState();
            loadSortState();
            renderFieldSelector();
            renderSortSelector();

            $('body').addClass('wpil-review-open');
            $modal.removeClass('hidden').attr('aria-hidden','false');
        }

        function closeModal(){
            $('body').removeClass('wpil-review-open');
            $modal.addClass('hidden').attr('aria-hidden','true');
            pendingDecisionIds = {};
            if(isReviewProcessRunning()){
                fetchReviewCount();
            }
        }

        function getVisibleReviewCount(){
            return Object.keys(itemsById).length;
        }

        function getServerRemainingCount(){
            var total = (remainingCount !== undefined && remainingCount !== null) ? parseInt(remainingCount, 10) : 0;
            if(isNaN(total) || total < 0){
                total = 0;
            }

            return total;
        }

        function getDisplayedRemainingCount(){
            return Math.max(getServerRemainingCount(), getVisibleReviewCount());
        }

        function isReviewProcessRunning(){
            var $running = $('#wpil-ai-linking-running');
            return $running.length && $running.val() === '1';
        }

        function isReviewProcessComplete(){
            var $complete = $('#wpil-ai-linking-complete');
            return $complete.length && $complete.val() === '1';
        }

        function canFetchReviewCount(){
            if(!ajaxUrl){
                return false;
            }

            var processKey = getReviewProcessKey();
            if(!processKey){
                return false;
            }

            return isReviewProcessRunning() || isReviewProcessComplete();
        }

        function syncRemainingUi(){
            var total = getDisplayedRemainingCount();
            finishedLooking = (total <= 0);
            $remaining.text(total);
            updateReviewCta(total);
        }

        function updateFooterMeta(){
            var total = getVisibleReviewCount();

            // In your new flow: each item is decided immediately, so "selected" is basically "visible"
            $visible.text(total);
            $selected.text(total);

            syncRemainingUi();
            setEmptyState();
        }

        function temporarilyReduceRemainingCount(count){
            var removedCount = (count !== undefined && count !== null) ? parseInt(count, 10) : 0;
            if(isNaN(removedCount) || removedCount < 1){
                return;
            }

            var currentRemaining = (remainingCount !== undefined && remainingCount !== null) ? parseInt(remainingCount, 10) : getDisplayedRemainingCount();
            if(isNaN(currentRemaining) || currentRemaining < 0){
                currentRemaining = getDisplayedRemainingCount();
            }

            remainingCount = Math.max(0, currentRemaining - removedCount);
            syncRemainingUi();
            setEmptyState();
        }

        function updateReviewCta(count){
            var total = (count !== undefined && count !== null) ? parseInt(count, 10) : 0;
            if(isNaN(total) || total < 0){
                total = 0;
            }
            var shouldShowSpinner = (total <= 0 && isReviewProcessRunning() && !reviewCountPollingPaused);

            if($reviewReadyCount.length){
                $reviewReadyCount.text(total);
            }

            if(!$reviewButton.length){
                return;
            }

            if(total > 0){
                $reviewButton.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                if($reviewButtonLabel.length){
                    $reviewButtonLabel.text('Review');
                }
                if($reviewButtonSpinner.length){
                    $reviewButtonSpinner.addClass('hidden');
                }
            }else{
                $reviewButton.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                if($reviewButtonLabel.length){
                    $reviewButtonLabel.text('Review');
                }
                if($reviewButtonSpinner.length){
                    $reviewButtonSpinner.toggleClass('hidden', !shouldShowSpinner);
                }
            }
        }

        function fetchReviewCount(){
            if(reviewCountPollingPaused){
                return;
            }

            if(!canFetchReviewCount()){
                stopReviewCountPolling();
                return;
            }

            var nonce = $('#wpil-scanning-nonce').val();
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'wpil_get_review_link_count',
                    nonce: nonce,
                    process_key: getReviewProcessKey(),
                    fix_type: getReviewFixType()
                }
            }).done(function(resp){
                if(resp && resp.success && resp.data && resp.data.remaining !== undefined){
                    remainingCount = parseInt(resp.data.remaining, 10);
                    if(isNaN(remainingCount) || remainingCount < 0){
                        remainingCount = 0;
                    }
                    syncRemainingUi();
                    setEmptyState();
                }
            });
        }

        function startReviewCountPolling(){
            if(reviewCountPollingPaused){
                return;
            }
            if(!canFetchReviewCount()){
                stopReviewCountPolling();
                return;
            }

            fetchReviewCount();
            if(!isReviewProcessRunning()){
                stopReviewCountPolling();
                return;
            }

            if(!reviewCountTimer){
                reviewCountTimer = setInterval(function(){
                    if(reviewCountPollingPaused){
                        return;
                    }
                    if(!isReviewProcessRunning()){
                        stopReviewCountPolling();
                        fetchReviewCount();
                        return;
                    }
                    if($modal.hasClass('hidden')){
                        fetchReviewCount();
                    }
                }, 6000);
            }
        }

        function stopReviewCountPolling(){
            if(reviewCountTimer){
                clearInterval(reviewCountTimer);
                reviewCountTimer = null;
            }
        }

        function setReviewCountPollingPaused(paused){
            reviewCountPollingPaused = !!paused;
            window.WPIL_REVIEW_COUNT_POLLING_PAUSED = reviewCountPollingPaused ? 1 : 0;

            if(reviewCountPollingPaused){
                stopReviewCountPolling();
                if($reviewButtonSpinner.length){
                    $reviewButtonSpinner.addClass('hidden');
                }
            }
        }

        function getSelectedFields(){
            var stored = localStorage.getItem(fieldStorageKey);
            if(stored){
                try{
                    var parsed = JSON.parse(stored);
                    if(Array.isArray(parsed) && parsed.length){
                        return parsed;
                    }
                }catch(e){}
            }

            var defaults = [];
            for(var i = 0; i < fieldOptions.length; i++){
                if(fieldOptions[i].default){
                    defaults.push(fieldOptions[i].key);
                }
            }
            return defaults;
        }

        function getFieldLabelByKey(key){
            for(var i = 0; i < fieldOptions.length; i++){
                if(fieldOptions[i].key === key){
                    return fieldOptions[i].label;
                }
            }
            return key;
        }

        function getDefaultSortDirection(key){
            return numericSortKeys[key] ? 'desc' : 'asc';
        }

        function getAvailableSortKeys(){
            var selected = getSelectedFields();
            var available = [];

            for(var i = 0; i < selected.length; i++){
                if(selected[i] === 'view_link'){
                    continue;
                }
                if(available.indexOf(selected[i]) === -1){
                    available.push(selected[i]);
                }
            }

            if(available.indexOf('ai') === -1){
                available.unshift('ai');
            }

            return available;
        }

        function loadSortState(){
            var available = getAvailableSortKeys();
            currentSortKey = 'ai';
            currentSortDir = getDefaultSortDirection('ai');

            var stored = localStorage.getItem(sortStorageKey);
            if(!stored){
                return;
            }

            try{
                var parsed = JSON.parse(stored);
                if(parsed && available.indexOf(parsed.key) !== -1){
                    currentSortKey = parsed.key;
                    currentSortDir = (parsed.dir === 'asc' || parsed.dir === 'desc') ? parsed.dir : getDefaultSortDirection(parsed.key);
                }
            }catch(e){}
        }

        function persistSortState(){
            localStorage.setItem(sortStorageKey, JSON.stringify({
                key: currentSortKey,
                dir: currentSortDir
            }));
        }

        function ensureValidSortState(){
            var available = getAvailableSortKeys();
            if(available.indexOf(currentSortKey) === -1){
                currentSortKey = 'ai';
                currentSortDir = getDefaultSortDirection('ai');
                persistSortState();
            }
        }

        function renderSortSelector(){
            var $wrap = $('#wpil-review-sort-select');
            if(!$wrap.length){
                return;
            }

            ensureValidSortState();
            var available = getAvailableSortKeys();
            var sortLabel = getFieldLabelByKey(currentSortKey);
            var dirArrow = currentSortDir === 'asc' ? '↑' : '↓';
            var html = ''
                + '<div class="wpil-review-sort-menu">'
                +   '<button type="button" class="wpil-review-sort-toggle" data-review-sort-toggle="1">Sort: ' + escapeHtml(sortLabel) + ' ' + dirArrow + '</button>'
                +   '<div class="wpil-review-sort-menu-panel">';

            for(var i = 0; i < available.length; i++){
                var key = available[i];
                var isActive = key === currentSortKey;
                var label = getFieldLabelByKey(key);
                html += ''
                    + '<button type="button" class="wpil-review-sort-option' + (isActive ? ' is-active' : '') + '" data-review-sort-key="' + escapeAttr(key) + '">'
                    + escapeHtml(label)
                    + (isActive ? ' ' + dirArrow : '')
                    + '</button>';
            }

            html += '</div></div>';
            $wrap.html(html);
        }

        function getSortableValue(item, key){
            var data = (item && item.target_data) ? item.target_data : {};
            if(key === 'ai'){
                var score = (item && item.ai_relation_score !== undefined && item.ai_relation_score !== null) ? parseFloat(item.ai_relation_score) : NaN;
                return isNaN(score) ? null : score;
            }
            if(key === 'type'){
                return data.type || '';
            }
            if(key === 'tax'){
                return data.categories || data.tags || data.taxonomy || '';
            }
            if(key === 'inbound'){
                return parseInt(data.inbound_internal, 10);
            }
            if(key === 'outbound_internal'){
                return parseInt(data.outbound_internal, 10);
            }
            if(key === 'outbound_external'){
                return parseInt(data.outbound_external, 10);
            }
            if(key === 'post_id'){
                return parseInt(data.post_id, 10);
            }
            if(key === 'language'){
                return data.language || '';
            }
            return '';
        }

        function compareItems(aItem, bItem){
            var aVal = getSortableValue(aItem, currentSortKey);
            var bVal = getSortableValue(bItem, currentSortKey);
            var dir = currentSortDir === 'asc' ? 1 : -1;
            var isNumeric = !!numericSortKeys[currentSortKey];

            if(isNumeric){
                var aNum = (typeof aVal === 'number' && !isNaN(aVal)) ? aVal : -Infinity;
                var bNum = (typeof bVal === 'number' && !isNaN(bVal)) ? bVal : -Infinity;
                if(aNum !== bNum){
                    return (aNum - bNum) * dir;
                }
            }else{
                var aStr = String(aVal || '').toLowerCase();
                var bStr = String(bVal || '').toLowerCase();
                if(aStr !== bStr){
                    return aStr > bStr ? dir : -dir;
                }
            }

            var aScore = (aItem && aItem.ai_relation_score !== undefined && aItem.ai_relation_score !== null) ? parseFloat(aItem.ai_relation_score) : -Infinity;
            var bScore = (bItem && bItem.ai_relation_score !== undefined && bItem.ai_relation_score !== null) ? parseFloat(bItem.ai_relation_score) : -Infinity;
            if(aScore !== bScore){
                return (bScore - aScore);
            }

            return 0;
        }

        function setSelectedFields(list){
            localStorage.setItem(fieldStorageKey, JSON.stringify(list || []));
        }

        function isFieldSelected(key){
            return getSelectedFields().indexOf(key) !== -1;
        }

        function renderFieldSelector(){
            var $wrap = $('#wpil-review-data-select');
            if(!$wrap.length){
                return;
            }

            var selected = getSelectedFields();
            var countLabel = selected.length ? (selected.length + ' fields') : 'No fields';
            var html = ''
                + '<div class="wpil-review-field-menu">'
                +   '<button type="button" class="wpil-review-field-toggle" data-review-field-toggle="1">Post Data Fields: ' + escapeHtml(countLabel) + ' ▼</button>'
                +   '<div class="wpil-review-field-menu-panel">';

            for(var i = 0; i < fieldOptions.length; i++){
                var opt = fieldOptions[i];
                var isActive = selected.indexOf(opt.key) !== -1;
                html += ''
                    + '<label class="wpil-review-field">'
                    + '<input type="checkbox" data-field="' + opt.key + '"' + (isActive ? ' checked' : '') + '>'
                    + '<span>' + escapeHtml(opt.label) + '</span>'
                    + '</label>';
            }

            html += '</div></div>';
            $wrap.html(html);
        }

        function updateFieldToggleLabel(){
            var selected = getSelectedFields();
            var countLabel = selected.length ? (selected.length + ' fields') : 'No fields';
            $('#wpil-review-data-select [data-review-field-toggle]').text('Post Data Fields: ' + countLabel + ' ▼');
        }

        $(document).on('change', '#wpil-review-data-select input[type=\"checkbox\"]', function(){
            var selected = getSelectedFields();
            var key = $(this).data('field');

            if(this.checked){
                if(selected.indexOf(key) === -1){
                    selected.push(key);
                }
            }else{
                selected = selected.filter(function(item){ return item !== key; });
            }

            setSelectedFields(selected);
            updateFieldToggleLabel();
            renderSortSelector();
            renderAll();
        });

        $(document).on('click', '[data-review-field-toggle]', function(e){
            e.preventDefault();
            var $toggle = $(this);
            var $panel = $toggle.closest('.wpil-review-field-menu').find('.wpil-review-field-menu-panel');
            var isOpen = $panel.hasClass('is-open');

            $('.wpil-review-field-menu-panel').removeClass('is-open');
            $('.wpil-review-field-toggle').removeClass('is-open');

            if(!isOpen){
                $panel.addClass('is-open');
                $toggle.addClass('is-open');
            }
        });

        $(document).on('click', '[data-review-sort-toggle]', function(e){
            e.preventDefault();
            var $toggle = $(this);
            var $panel = $toggle.closest('.wpil-review-sort-menu').find('.wpil-review-sort-menu-panel');
            var isOpen = $panel.hasClass('is-open');

            $('.wpil-review-sort-menu-panel').removeClass('is-open');
            $('.wpil-review-sort-toggle').removeClass('is-open');
            $('.wpil-review-field-menu-panel').removeClass('is-open');
            $('.wpil-review-field-toggle').removeClass('is-open');

            if(!isOpen){
                $panel.addClass('is-open');
                $toggle.addClass('is-open');
            }
        });

        $(document).on('click', '[data-review-sort-key]', function(e){
            e.preventDefault();
            var key = String($(this).data('review-sort-key') || '');
            if(!key){
                return;
            }

            if(key === currentSortKey){
                currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
            }else{
                currentSortKey = key;
                currentSortDir = getDefaultSortDirection(key);
            }

            persistSortState();
            renderSortSelector();
            refreshSuggestionsForSortChange();
        });

        $(document).on('click', function(e){
            if($(e.target).closest('.wpil-review-field-menu').length < 1){
                $('.wpil-review-field-menu-panel').removeClass('is-open');
                $('.wpil-review-field-toggle').removeClass('is-open');
            }
            if($(e.target).closest('.wpil-review-sort-menu').length < 1){
                $('.wpil-review-sort-menu-panel').removeClass('is-open');
                $('.wpil-review-sort-toggle').removeClass('is-open');
            }
        });

        function cardHtml(item){
            var id = String(item.id);
            var isPending = !!pendingDecisionIds[id];
            var postTitle = escapeHtml(item.post_title || '');
            var sourceViewLink = String(item.source_view_link || '');
            var postTitleHtml = postTitle;
            if(sourceViewLink){
                postTitleHtml = '<a href="' + escapeAttr(sourceViewLink) + '" target="_blank" rel="noopener">' + postTitle + '</a>';
            }
            var sentence  = item.proposed_sentence_html ? sanitizeProposedHtml(item.proposed_sentence_html) : '';

            // Optional fields (see PHP section below)
            var targetTitle = escapeHtml(item.target_title || '');
            var targetHint  = escapeHtml(item.target_hint || '');
            var aiRelationScore = item.ai_relation_score !== null && item.ai_relation_score !== undefined && item.ai_relation_score > 0 ? (Math.round(item.ai_relation_score * 100)) + '%' : 'Unknown';
            var metaHtml = buildMetaHtml(item);
            return ''
            + '<div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200 flex flex-col md:flex-row items-center gap-6 transition-all' + (isPending ? ' opacity-60 pointer-events-none' : '') + '" data-link-id="'+id+'">'
                + '<div class="flex-1 min-w-0">'
                + '<div class="flex items-center gap-2 mb-2">'
                    + '<span class="text-[10px] uppercase font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">Source Post: '+postTitleHtml+'</span>'
                + '</div>'
                + '<p class="text-gray-700 leading-relaxed">'
                    + (sentence ? '“'+sentence+'”' : '<em class="text-gray-500 font-bold">No sentence available</em>')
                + '</p>'
                + '</div>'

                + '<div class="hidden md:block text-gray-300">'
                + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">'
                    + '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />'
                + '</svg>'
                + '</div>'

                + '<div class="w-full md:w-1/4 min-w-0">'
                + '<div class="text-[10px] uppercase font-bold text-gray-400 mb-1">Linking to:</div>'
                + '<div class="font-medium text-gray-900 truncate">'+(targetTitle || 'Suggested page')+'</div>'
                + (targetHint ? '<div class="text-xs text-green-600 mt-0.5">'+targetHint+'</div>' : '')
                + (isFieldSelected('ai') && aiRelationScore !== null ? '<div class="text-xs text-purple-600 mt-1"><strong>AI Score:</strong> '+aiRelationScore+'</div>' : '')
                + (metaHtml ? metaHtml : '')
                + '</div>'

                + '<div class="flex items-center gap-3 border-l border-gray-100 pl-6">'
                + '<button type="button" class="p-2 rounded-full ring-2 ring-gray-300 hover:ring-red-300 hover:bg-red-50 hover:text-red-500 transition-colors" data-action="reject" title="Reject"' + (isPending ? ' disabled aria-disabled="true"' : '') + '>'
                    + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">'
                    + '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />'
                    + '</svg>'
                + '</button>'

                + '<button type="button" class="p-2 rounded-full ring-2 ring-gray-300 hover:ring-green-400 hover:bg-green-100 hover:text-green-600 transition-colors" data-action="approve" title="Approve"' + (isPending ? ' disabled aria-disabled="true"' : '') + '>'
                    + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">'
                    + '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />'
                    + '</svg>'
                + '</button>'
                + '</div>'
            + '</div>';
        }

        function buildMetaHtml(item){
            var data = item.target_data || {};
            var selected = getSelectedFields();
            if(!selected.length){
                return '';
            }

            var lines = [];

            if(selected.indexOf('type') !== -1 && data.type){
                lines.push('<div><b>Type:</b> ' + escapeHtml(data.type) + '</div>');
            }

            if(selected.indexOf('tax') !== -1){
                if(data.categories){
                    lines.push('<div><b>Categories:</b> ' + escapeHtml(data.categories) + '</div>');
                }
                if(data.tags){
                    lines.push('<div><b>Tags:</b> ' + escapeHtml(data.tags) + '</div>');
                }
                if(!data.categories && !data.tags && data.taxonomy){
                    lines.push('<div><b>Taxonomy:</b> ' + escapeHtml(data.taxonomy) + '</div>');
                }
            }

            if(selected.indexOf('inbound') !== -1){
                var inboundVal = (data.inbound_internal !== undefined && data.inbound_internal !== null && data.inbound_internal !== '') ? data.inbound_internal : 0;
                lines.push('<div><b>Inbound Internal Links:</b> ' + escapeHtml(inboundVal) + '</div>');
            }

            if(selected.indexOf('outbound_internal') !== -1){
                var outboundInternalVal = (data.outbound_internal !== undefined && data.outbound_internal !== null && data.outbound_internal !== '') ? data.outbound_internal : 0;
                lines.push('<div><b>Outbound Internal Links:</b> ' + escapeHtml(outboundInternalVal) + '</div>');
            }

            if(selected.indexOf('outbound_external') !== -1){
                var outboundExternalVal = (data.outbound_external !== undefined && data.outbound_external !== null && data.outbound_external !== '') ? data.outbound_external : 0;
                lines.push('<div><b>Outbound External Links:</b> ' + escapeHtml(outboundExternalVal) + '</div>');
            }

            if(selected.indexOf('post_id') !== -1 && data.post_id){
                lines.push('<div><b>Post ID:</b> ' + escapeHtml(data.post_id) + '</div>');
            }

            if(selected.indexOf('language') !== -1 && data.language){
                lines.push('<div><b>Language Code:</b> ' + escapeHtml(data.language) + '</div>');
            }

            if(selected.indexOf('view_link') !== -1 && data.view_link){
                lines.push('<div><b>View Link:</b> <a target="_blank" rel="noopener" href="' + escapeAttr(data.view_link) + '">' + escapeHtml(data.view_link) + '</a></div>');
            }

            if(!lines.length){
                return '';
            }

            return '<div class="wpil-review-meta">' + lines.join('') + '</div>';
        }

        function renderAll(){
            var ids = Object.keys(itemsById);
            ensureValidSortState();
            ids.sort(function(a, b){
                var aItem = itemsById[a] || {};
                var bItem = itemsById[b] || {};
                return compareItems(aItem, bItem);
            });
            var html = '';
            for(var i=0;i<ids.length;i++){
            html += cardHtml(itemsById[ids[i]]);
            }
            $list.html(html);
            updateFooterMeta();
        }

        function syncVisibleItems(newItems){
            var nextItems = {};
            var visibleCount = 0;

            Object.keys(itemsById).forEach(function(id){
                if(pendingDecisionIds[id] && itemsById[id]){
                    nextItems[id] = itemsById[id];
                    visibleCount++;
                }
            });

            for(var i = 0; i < newItems.length; i++){
                if(visibleCount >= MAX_VISIBLE_ITEMS){
                    break;
                }

                var it = newItems[i];
                if(!it || it.id === undefined || it.id === null){
                    continue;
                }
                if(isSuppressedItem(it)){
                    continue;
                }

                var id = String(it.id);
                if(pendingDecisionIds[id]){
                    continue;
                }

                nextItems[id] = it;
                visibleCount++;
            }

            itemsById = nextItems;
            renderAll();
        }

        function removeItem(id, deferUiUpdate){
            id = String(id);
            if(!itemsById[id]){
                if(!deferUiUpdate){
                    updateFooterMeta();
                }
                return 0;
            }

            delete itemsById[id];
            if(!deferUiUpdate){
                updateFooterMeta();
            }

            return 1;
        }

        function buildSentenceKey(item){
            if(!item){
                return '';
            }

            var postId = parseInt(item.post_id, 10);
            var postType = String(item.post_type || '');
            var sentenceId = String(item.sentence_id || '');
            if(!postId || !postType || !sentenceId){
                return '';
            }

            return postType + '|' + postId + '|' + sentenceId;
        }

        function buildSourceTargetKey(item){
            if(!item){
                return '';
            }

            var postId = parseInt(item.post_id, 10);
            var postType = String(item.post_type || '');
            var targetId = parseInt(item.target_id, 10);
            var targetType = String(item.target_type || '');
            if(!postId || !postType || !targetId || !targetType){
                return '';
            }

            return postType + '|' + postId + '|' + targetType + '|' + targetId;
        }

        function suppressRelatedKeys(item){
            var sentenceKey = buildSentenceKey(item);
            if(sentenceKey){
                suppressedSentenceKeys[sentenceKey] = true;
            }

            var sourceTargetKey = buildSourceTargetKey(item);
            if(sourceTargetKey){
                suppressedPairKeys[sourceTargetKey] = true;
            }
        }

        function isSuppressedItem(item){
            var sentenceKey = buildSentenceKey(item);
            if(sentenceKey && suppressedSentenceKeys[sentenceKey]){
                return true;
            }

            var sourceTargetKey = buildSourceTargetKey(item);
            if(sourceTargetKey && suppressedPairKeys[sourceTargetKey]){
                return true;
            }

            return false;
        }

        function removeSiblingItemsFromPanel(baseItem, deferUiUpdate){
            if(!baseItem){
                if(!deferUiUpdate){
                    updateFooterMeta();
                }
                return 0;
            }

            var sentenceKey = buildSentenceKey(baseItem);
            var sourceTargetKey = buildSourceTargetKey(baseItem);
            var ids = Object.keys(itemsById);
            var removedCount = 0;

            for(var i = 0; i < ids.length; i++){
                var id = String(ids[i] || '');
                var item = itemsById[id];
                if(!item){
                    continue;
                }

                var sentenceMatch = (sentenceKey !== '' && buildSentenceKey(item) === sentenceKey);
                var sourceTargetMatch = (sourceTargetKey !== '' && buildSourceTargetKey(item) === sourceTargetKey);
                if(sentenceMatch || sourceTargetMatch){
                    delete itemsById[id];
                    removedCount++;
                }
            }

            if(!deferUiUpdate && removedCount > 0){
                renderAll();
            }else if(!deferUiUpdate){
                updateFooterMeta();
            }

            return removedCount;
        }

        function refreshSuggestionsForSortChange(){
            itemsById = {};
            renderAll();
            setEmptyState();
            fetchSuggestions();
        }

        function fetchSuggestions(){
            if(isPolling) return;
            if(!ajaxUrl) return;

            isPolling = true;
            var nonce = $('#wpil-scanning-nonce').val();

            $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'wpil_get_review_links',
                nonce: nonce,
                process_key: getReviewProcessKey(),
                fix_type: getReviewFixType(),
                sort_key: currentSortKey || 'ai',
                sort_dir: currentSortDir || 'desc'
            }
            }).done(function(resp){
                if(resp && resp.success && resp.data && Array.isArray(resp.data.items)){
                    if(resp.data.remaining !== undefined){
                        remainingCount = parseInt(resp.data.remaining, 10);
                        if(isNaN(remainingCount) || remainingCount < 0){
                            remainingCount = 0;
                        }
                        syncRemainingUi();
                    }

                    if(resp.data.items.length){
                        hasEverLoaded = true;
                    }

                    syncVisibleItems(resp.data.items);

                    // if no items came back, still refresh empty state (message might change)
                    setEmptyState();
                }
            }).always(function(){
                isPolling = false;
            });
        }

        function sendDecision(id, decision){
            var nonce = $('#wpil-scanning-nonce').val();
            return $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'wpil_set_review_link_decision',
                    nonce: nonce,
                    link_id: id,
                    decision: decision,
                    process_key: getReviewProcessKey(),
                    fix_type: getReviewFixType()
                }
            });
        }

        function startPolling(){
            fetchSuggestions();
            if(!pollTimer){
                pollTimer = setInterval(fetchSuggestions, POLL_MS);
            }
        }

        function stopPolling(){
            if(pollTimer){
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function queueSuggestionRefresh(delay){
            var wait = parseInt(delay, 10);
            if(isNaN(wait) || wait < 0){
                wait = 1000;
            }

            if(suggestionRefreshTimer){
                clearTimeout(suggestionRefreshTimer);
                suggestionRefreshTimer = null;
            }

            stopPolling();
            suggestionRefreshTimer = setTimeout(function(){
                suggestionRefreshTimer = null;
                fetchSuggestions();
                if(!$modal.hasClass('hidden')){
                    startPolling();
                }
            }, wait);
        }

        function setEmptyState(){
            var total = Object.keys(itemsById).length;
            var hasKnownRemaining = (remainingCount !== null && remainingCount !== undefined);
            var normalizedRemaining = hasKnownRemaining ? parseInt(remainingCount, 10) : null;
            if(hasKnownRemaining && (isNaN(normalizedRemaining) || normalizedRemaining < 0)){
                normalizedRemaining = 0;
            }

            if(total > 0){
                $empty.addClass('hidden');
                return;
            }

            // show empty area
            $empty.removeClass('hidden');

            // Decide which message
            if(isReviewProcessRunning()){
                $emptyText.text(hasEverLoaded ? 'Looking for more links…' : 'Looking for links…');
                $empty.find('svg').removeClass('hidden');
            } else if(hasKnownRemaining && normalizedRemaining <= 0){
                $emptyText.text('No links left to review.');
                $empty.find('svg').addClass('hidden'); // stop showing spinner
            } else {
                // still working / waiting
                $emptyText.text(hasEverLoaded ? 'Looking for more links…' : 'Looking for links…');
                $empty.find('svg').removeClass('hidden');
            }
        }

        function finalizeDecisionRemoval(linkId, decision, selectedItem, refreshAfter){
            var removedCount = removeItem(linkId, true);
            renderAll();
            temporarilyReduceRemainingCount(removedCount);
            if(refreshAfter !== false){
                fetchSuggestions();
            }
        }

        function resetPendingDecision(linkId){
            delete pendingDecisionIds[String(linkId)];
            renderAll();
        }

        // Click approve/reject -> remove it right away and let the refresh sort out anything unexpected
        $list.on('click', '[data-action]', function(){
            var decision = $(this).data('action');
            var $card = $(this).closest('[data-link-id]');
            var linkId = $card.data('link-id');
            var selectedItem = itemsById[String(linkId)] || null;
            finalizeDecisionRemoval(linkId, decision, selectedItem, false);

            sendDecision(linkId, decision).done(function(resp){
                if(!resp || !resp.success || !resp.data){
                    queueSuggestionRefresh(1000);
                    return;
                }

                queueSuggestionRefresh(1000);
            }).fail(function(xhr){
                queueSuggestionRefresh(1000);
                console.warn('Decision failed', xhr.status, xhr.responseText);
            });
        });

        // Close buttons / backdrop
        $modal.on('click', '[data-wpil-review-close]', function(){
            // If user closes manually, keep them in review mode but stop polling so we don’t run forever
            stopPolling();
            if($(this).hasClass('wpil-review-modal__close')){
                $(document).trigger('wpil:review_modal_close_request');
            }
            closeModal();
        });

        // Ignore batch: reject only current visible items, keep panel open
        $modal.on('click', '[data-wpil-review-skip]', function(){
            applyDecisionToVisible('reject');
        });

        // Auto Approve All: approve only current visible items, keep panel open
        $('#wpil-review-auto-approve-all').on('click', function(){
            applyDecisionToVisible('approve');
        });

        $reviewButton.on('click', function(){
            var $aiToggle = $('#wpil-ai-linking-toggle');
            if($aiToggle.length && !$aiToggle.is(':checked')){
                return;
            }

            setLinkMode('review');
            openModal();
            startPolling();
        });

        $(document).on('change', 'input[name="wpil-ai-linking-mode"]', function(){
            var mode = (this.value === 'review') ? 'review' : 'auto';
            setLinkMode(mode);
            updateReviewSectionVisibility(mode);

            if(mode === 'auto'){
                stopPolling();
                closeModal();
            }
        });

        setLinkMode(getLinkMode(), {silent: true});
        updateReviewSectionVisibility(getLinkMode());

        function setLinkMode(mode){
            $linkMode.val(mode);
            syncModeRadios(mode);

            var isSilent = arguments.length > 1 && arguments[1] && arguments[1].silent;
            if(!isSilent){
                $(document).trigger('wpil:link_mode_changed', [mode]);
            }
        }

        function getLinkMode(){
            var $checked = $('input[name="wpil-ai-linking-mode"]:checked');
            var mode = $checked.length ? $checked.val() : $linkMode.val();
            return mode ? mode : 'auto';
        }

        function syncModeRadios(mode){
            var $auto = $('#wpil-ai-linking-mode-auto');
            var $review = $('#wpil-ai-linking-mode-review');

            if($auto.length){
                $auto.prop('checked', mode === 'auto');
            }
            if($review.length){
                $review.prop('checked', mode === 'review');
            }
        }

        function updateReviewSectionVisibility(mode){
            if(!$reviewSection.length) return;

            if(mode === 'review'){
                $reviewSection.removeClass('hidden');
                if(canFetchReviewCount()){
                    startReviewCountPolling();
                }else{
                    stopReviewCountPolling();
                    syncRemainingUi();
                    setEmptyState();
                }
            }else{
                $reviewSection.addClass('hidden');
                stopReviewCountPolling();
            }
        }

        $(document).on('wpil:fix_started', function(){
            setReviewCountPollingPaused(false);
            if(getLinkMode() === 'review'){
                startReviewCountPolling();
            }
            setEmptyState();
        });

        $(document).on('wpil:fix_finished wpil:fix_error', function(){
            setTimeout(function(){
                stopReviewCountPolling();
                if(getLinkMode() === 'review' && canFetchReviewCount()){
                    fetchReviewCount();
                }else{
                    syncRemainingUi();
                    setEmptyState();
                }
            }, 0);
        });

        function applyDecisionToVisible(decision){
            var ids = Object.keys(itemsById);

            if(!ids.length){
                return;
            }

            // Optimistic UI clear
            var removedCount = ids.length;
            itemsById = {};
            renderAll();
            temporarilyReduceRemainingCount(removedCount);

            // Send decisions in background (no need to block UI)
            for(var i=0; i<ids.length; i++){
                sendDecision(ids[i], decision);
            }

            // Refill
            queueSuggestionRefresh(1000);
        }

        // Keep the "ready" count in sync while scanning, without waiting for modal open.
        if(getLinkMode() === 'review'){
            startReviewCountPolling();
        }else{
            stopReviewCountPolling();
        }

        $(document).on('wpil:fix_cancelled', function(){
            setReviewCountPollingPaused(true);
            setEmptyState();
        });

    });

</script>
<div id="wpil-review-modal" class="wpil-review-modal hidden" aria-hidden="true">
  <div class="wpil-review-modal__backdrop" data-wpil-review-close="1"></div>

  <div class="wpil-review-modal__panel">
    <div class="wpil-review-modal__header">
        <div>
            <div class="text-2xl font-bold text-gray-900">Review Suggestions</div>
            <div class="text-gray-500 text-sm mt-1">
                <span class="text-[#7F5AF0] font-medium"><span data-role="remaining">0</span> links remaining</span>
            </div>
        </div>
        <div>
            <div class="flex items-center gap-3">
                <div id="wpil-review-sort-select" class="wpil-review-sort-list"></div>
                <div id="wpil-review-data-select" class="wpil-review-field-list"></div>
                <button type="button" class="wpil-review-modal__close" data-wpil-review-close="1" aria-label="Close">×</button>
            </div>
        </div>
    </div>

    <div class="wpil-review-modal__body">
      <div id="wpil-review-list" class="space-y-4"></div>

      <div id="wpil-review-empty-state" class="hidden text-center text-gray-500 py-10">
        <div class="flex items-center justify-center gap-2">
            <svg class="animate-spin h-4 w-4 text-[#7F5AF0]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" style="opacity: 0.25;"></circle>
            <path class="opacity-90" d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"></path>
            </svg>
            <span id="wpil-review-empty-text">Looking for links…</span>
        </div>
      </div>
    </div>

    <div class="wpil-review-modal__footer">
        <div></div>
      <div style="display:none" class="text-sm font-medium text-gray-500">
        <span class="font-bold text-gray-900" data-role="selected">0</span> out of <span data-role="visible">0</span> selected
      </div>

      <div class="flex items-center space-x-4">
        <button type="button" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2 rounded-lg border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition-colors" data-wpil-review-skip="1">
          Ignore This Batch
        </button>

        <button type="button" id="wpil-review-auto-approve-all"
          class="lw-gradient-bg text-white font-semibold text-sm px-4 py-2 rounded-lg shadow hover:shadow-md hover:opacity-95 transition-all flex items-center transform active:scale-95">
          <span>Approve These Links</span>
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="hidden w-5 h-5 ml-2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</div>







