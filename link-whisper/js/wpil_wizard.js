"use strict";

(function ($) {
    // we're going to do this via Ajax, sliding from page to page wihtout reloading

    // link handler
    $(document).on('click', '.wpil-wizard-link', handleLinkClick);
    function handleLinkClick(e){
        e.preventDefault();
        var pageId = $(this).data('wpil-wizard-link-id');

        if(pageId && pageId.length > 0){
            changePage(pageId);
        }
        console.log(this);
    }

    // link handler
    $(document).on('click', '.wpil-get-gsc-access-token', function (){
//        saveAboutYouInfo(1);
    });

    $(document).ready(function($) {
        // if we've just navved back from the GSC auth screen
        var params = new URLSearchParams(window.location.search);
        if(params.has('access_valid')){
            // run the ai page!
            changePage('connect-openai');
        }
    });

    // switch pager
    function changePage(pageId = ''){
        var page = $('.wizard-' + pageId);

        // first hide all the pages
        $('.wpil-wizard-page').addClass('wpil-wizard-page-hidden');

        // then hide any spinners
        $('.wpil-setup-wizard-loading').css({'display': 'none'});

        // now show the changed page
        page.removeClass('wpil-wizard-page-hidden');

        // if the page is the setup runner
        if(pageId === 'run-setup'){
            // clear the process tracker and run the installation
            clearProcessTracker();
        }
    }

    // process runner // runs the loading scan and processor

    // completion handler

    // button highlighter
    $(document).on('click', '.wpil-setup-wizard-radio', handleButtonClick);
    function handleButtonClick(){
        var button = $(this),
            page = button.parents('.wpil-setup-wizard:visible');
        // remove the checked clase from any active buttons
        button.parents('.wpil-setup-wizard-radio-button-wrapper').find('.wpil-setup-wizard-radio-button').removeClass('checked');
        // and tag our clicked button with the checked class
        button.parents('.wpil-setup-wizard-radio-button').addClass('checked');
        // if the radios are all selected
        if(checkRequiredRadios()){
            // enable the next stage button
            page.find('.wpil-setup-wizard-main-button').removeClass('button-disabled');
        }
    }

    function checkRequiredRadios(){
        var buttons = $('.wpil-setup-wizard-radio:visible'),
            checked = [],
            names = [];


        buttons.each(function(ind, element){
            var name = element.name;
            if(!$('[name="' + name + '"]').is('[required]')){
                return;
            }

            if(names.indexOf(name) === -1){
                names.push(name);
                if($('[name="' + name + '"]').is(':checked')){
                    checked.push(name);
                }
            }
        });

        return names.length === checked.length;
    }

    function getCheckedRadios(){
        var buttons = $('.wpil-setup-wizard-radio'),
            checked = {},
            names = [];

        buttons.each(function(ind, element){
            var name = element.name;
            if(!$('[name="' + name + '"]').is('[required]')){
                return;
            }

            if(names.indexOf(name) === -1){
                names.push(name);
                var selected = $('[name="' + name + '"]:checked');
                if(selected.length){
                    checked[name] = selected.val();
                }
            }
        });

        return checked;
    }

    var calling = false;
    $(document).on('change, input, blur, paste, keyup', '#wpil_license_key', function(){
        var input = $(this);
        if(input.val().length < 32){
            return;
        }

        clearTimeout(calling);
        calling = setTimeout(function(){
            $('#wpil_license_key').prop('disabled', true);
            $('#wpil-setup-wizard-license-activate').trigger('submit');
        }, 500);
    });
    
    $(document).on('click', '.wpil-wizard-about-you-next-button', function(){
        if($(this).hasClass('button-disabled')){
            return;
        }
        changePage('automatic-linking');
    });

    $(document).on('click', '.wpil-wizard-automatic-linking-next-button', function(){
        if($(this).hasClass('button-disabled')){
            return;
        }
        changePage('connect-gsc');
    });

    function saveAboutYouInfo(temp = 0){
        //e.preventDefault();

        var button = $('.wpil-wizard-automatic-linking-next-button'),
            selected = getCheckedRadios();

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'wpil_wizard_save_settings',
                settings: selected,
                temp_save: temp,
                nonce: button.data('wpil-nonce')
            },
            success: function(response){
                console.log(response);
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
        });
    }

    $(document).on('change', '#wpil_open_ai_api_key', function(){
        var text = $(this);
        if(text.val().length > 0){
            $('.wpil-wizard-activate-oai-button').removeClass('button-disabled');
        }else{
            $('.wpil-wizard-activate-oai-button').addClass('button-disabled');
        }
    });

    $(document).on('click', '.wpil-wizard-activate-oai-button', activateOaiKey);
    function activateOaiKey(e){
        e.preventDefault();

        var button = $(this);

        if(button.hasClass('button-disabled')){
            return;
        }

        // disable button && create loading effect
        $('.wpil-setup-wizard-loading').css({'display': 'block'});

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'wpil_wizard_save_oai_key',
                key: $('#wpil_open_ai_api_key').val(),
                nonce: button.data('wpil-nonce')
            },
            success: function(response){
                console.log(response);

                if(response.status === 'valid'){
                    changePage('run-setup');
                }else{
                    // undisable button
                    $('.wpil-setup-wizard-loading').css({'display': 'none'});
                }
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
//				setupProcessingError(true);
            }
        });
    }

    function clearProcessTracker(){
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'wpil_clear_process_tracker'
            },
            success: function(response){
                console.log(response);
                runInstallation();
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
        });
    }

    function hasRunWizard(){
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'wpil_has_run_wizard'
            },
            success: function(response){
                console.log(response);
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
        });
    }
    hasRunWizard();

    var dashboardTooltip = null;
    function runInstallation(){
        clearTimeout(dashboardTooltip);
        // start the dashboard tooltip timeout
        dashboardTooltip = setTimeout(function(){
            $('#wpil-explain-page-button').trigger('click');
        }, 1000 * 10);

//        saveAboutYouInfo();
        runLinkScan();
        runKeywordScan();
        animateFunFacts();
        if(false){
            scanWithAI();
        }else{
            hideAILoader();
        }
    }

    /**
     * Keeps track of how the processing is doing
     **/
    var processingStatus = {
        'runLinkScan': false,
        'runKeywordScan': false
    };

    function checkInstallationComplete(){
        var allGreen = true;
        for(var i in processingStatus){
            if(!processingStatus[i]){
                allGreen = false;
            }
        }

        if(allGreen){
            // set a flag so the Dashboard is sure that we're done
            setCompletionFlag();
            window.location.href = dashboardURL;
            // redirect to Dashboard
        }
    }

    function runLinkScan(){
        processReportReset($('.wpil-wizard-reset-report-nonce').val(), 0, true);
    }

    function runKeywordScan(){
        wpil_target_keyword_reset_process(1, 1, true);
    }

    function scanWithAI(){

    }

    function hideAILoader(){
        $('.wpil-wizard-ai-scan-progress-header, .wpil-wizard-ai-calculation-progress-header, .wpil-wizard-ai-scan-progress-loader, .wpil-wizard-ai-calculation-progress-loader').css({'display': 'none'});
    }

    var funCycle = null;
    function animateFunFacts(){
        clearInterval(funCycle);
        funCycle = setInterval(cycleFunFacts, (1000 * 10));
    }

    var timeList = [];    
    function processReportReset(nonce = null, loopCount = 0, clearData = false){
        if(!nonce){
            return;
        }

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'reset_report_data',
                nonce: nonce,
                loop_count: loopCount,
                clear_data: clearData,
            },
            error: function (jqXHR, textStatus) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                wpil_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(wpil_report_next_step());
            },
            success: function(response){
                if(!isJSON(response)){
                    response = extractAndValidateJSON(response, ['error', 'links_to_process_count', 'data_setup_complete', 'loop_count', 'loading_screen', 'nonce', 'time']);
                }

                // if there was an error
                if(response.error){
                    wpil_swal(response.error.title, response.error.text, 'error');
                    return;
                }
                
                // if we've been around a couple times without processing links, there must have been an error
                if(!response.links_to_process_count && response.loop_count > 5){
                    wpil_swal('Data Reset Error', 'Link Whisper has tried a number of times to reset the report data, and it hasn\'t been able to complete the action.', 'error');
                    return;
                }

                // if the data has been successfully reset
                if(response.data_setup_complete){
                    // set the loading screen now that the data setup is complete
                    if(response.loading_screen){
                        $('.wpil-wizard-process-subtext').css({'display': 'none'});
                        $('.wpil-wizard-process-subtext.scan-post-links').css({'display': 'inline-block'});
                    }
                    // set the time
                    timeList.push(response.time);
                    // and call the data processing function to handle the data
                    processReportData(response.nonce, 0, 0, 0);
                }else{
                    // if we're not done processing links, go around again
                    processReportReset(response.nonce, (response.loop_count + 1), true);
                }
            }
        });
    }
    /**
     * Keeps track of the loop's progress in a global context so the scan is less susceptible to minor errors like timeouts
     **/
    var globalScan = {
        'nonce': '', 						// nonce
        'loop': 0, 							// loop count
        'link_posts_to_process_count': 0, 	// posts/cats to process count
        'processed': 0, 					// how many have been processed so far
        'link_posts_to_process_diff': 0,	// the difference between the number of posts to process and the ones that have been processed
        'meta_filled': false, 				// if the meta processing is complete
        'links_filled': false,				// if the link processing is complete
        'error_count': 0,					// the number of times the scan has errored
        'loops_unchanged': 0				// the number of loops we've gone over without a change in the total number of processed posts
    };

    /**
     * Process runner that handles the report data generation process.
     * Loops around until all the site's links are inserted into the LW link table
     **/
    function processReportData(	nonce = null, 
                                loopCount = 0, 
                                linkPostsToProcessCount = 0, 
                                linkPostsProcessed = 0, 
                                linkPostProcessDiff = 0,
                                metaFilled = false, 
                                linksFilled = false,
                                loopsUnchanged = 0,
                                resumeScan = false)
    {
        if(!nonce){
            return;
        }

        // initialize the stage clock. // The clock is useful for debugging
        if(loopCount < 1){
            if(timeList.length > 0){
                var lastTime = timeList.pop();
                timeList = [lastTime];
            }else{
                timeList = [];
            }
        }

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'process_report_data',
                nonce: nonce,
                loop_count: loopCount,
                link_posts_to_process_count: linkPostsToProcessCount,
                link_posts_processed: linkPostsProcessed,
                link_posts_to_process_diff: linkPostProcessDiff,
                meta_filled: metaFilled,
                links_filled: linksFilled,
                loops_unchanged: loopsUnchanged,
                resume_scan: (resumeScan) ? 1: 0 
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log('There has been an error during the scan!');
                console.log(globalScan);
                globalScan.error_count += 1;

                // if the scan has errored less than 5 times, try it again
                if(globalScan.error_count < 5){
                    processReportData(
                        globalScan.nonce,
                        globalScan.loop,
                        globalScan.link_posts_to_process_count,
                        globalScan.processed,
                        globalScan.link_posts_to_process_diff,
                        globalScan.meta_filled,
                        globalScan.links_filled,
                        globalScan.loops_unchanged
                    );
                }else{
                    var wrapper = document.createElement('div');
                    $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                    $(wrapper).append(jqXHR.responseText);
                    wpil_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(wpil_report_next_step());
                }
            },
            success: function(response){
                console.log(response);

                if(!isJSON(response)){
                    response = extractAndValidateJSON(response, [
                        'error', 
                        'links_to_process_count', 
                        'data_setup_complete', 
                        'loop_count', 
                        'loading_screen',
                        'processed',
                        'meta_filled',
                        'links_filled',
                        'error_count',
                        'loops_unchanged',
                        'processing_complete',
                        'nonce', 
                        'time']);
                }

                // if there was an error
                if(response.error){
                    // output the error message
                    wpil_swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

                // log the time
                timeList.push(response.time);

                // update the global stats
                globalScan.nonce = response.nonce;
                globalScan.loop = 0;
                globalScan.link_posts_to_process_count = response.link_posts_to_process_count;
                globalScan.processed = response.link_posts_processed;
                globalScan.link_posts_to_process_diff = response.link_posts_to_process_diff;
                globalScan.meta_filled = response.meta_filled;
                globalScan.links_filled = response.links_filled;
                globalScan.error_count = 0;
                globalScan.loops_unchanged = response.loops_unchanged;

                // if the meta has been successfully processed
                if(response.processing_complete){
                    // if the processing is complete
                    // console.log the time if available
                    if(timeList > 1){
                        console.log('The post processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
                    }

                    // update the loading bar one more time
                    animateTheReportLoadingBar(response, '.wpil-wizard-post-progress-loader', true);

                    // note the success in the status object
                    processingStatus['runLinkScan'] = true;

                    // ping the checker to see if we should redirect now
                    checkInstallationComplete();

                    // and exit since we're done here
                    return;
                } else if(response.link_processing_complete){
                    // if we've finished loading links into the link table
                    // show the post processing loading page
                    if(response.loading_screen){
                        $('.wpil-wizard-process-subtext').css({'display': 'none'});
                        $('.wpil-wizard-process-subtext.calc-post-links').css({'display': 'inline-block'});
                    }

                    // console.log the time if available
                    if(timeList > 1){
                        console.log('The link processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
                    }

                    // re-call the function for the final round of processing
                    processReportData(  response.nonce,
                        0,
                        response.link_posts_to_process_count,
                        0,
                        response.link_posts_to_process_diff,
                        response.meta_filled,
                        response.links_filled,
                        response.loops_unchanged);

                } else if(response.meta_filled){
                    // show the link processing loading screen
                    if(response.loading_screen){
                        $('.wpil-wizard-process-subtext').css({'display': 'none'});
                        $('.wpil-wizard-process-subtext.scan-post-links').css({'display': 'inline-block'});
//                        $('#wpbody-content').html(response.loading_screen);
                    }
                    // console.log the time if available
                    if(timeList > 1){
                        console.log('The meta processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
                    }

                    // update the loading bar
                    animateTheReportLoadingBar(response, '.wpil-wizard-post-progress-loader', response.processing_complete);

                    // and recall the function to begin the link processing (loading the site's links into the link table)
                    processReportData(  response.nonce,                         // nonce
                        0,                                      // loop count
                        response.link_posts_to_process_count,   // posts/cats to process count
                        0,                                      // how many have been processed so far
                        response.link_posts_to_process_diff,	// what's the difference between the posts processed and the ones coming up
                        response.meta_filled,                   // if the meta processing is complete
                        response.links_filled,					// if the link processing is complete
                        response.loops_unchanged);				// how many loops have we gone through without processing posts
                } else{
                    // update the loop count
                    globalScan.loop = (response.loop_count + 1);
                    // if we're not done processing, go around again
                    processReportData(  response.nonce, 
                                        (response.loop_count + 1), 
                                        response.link_posts_to_process_count, 
                                        response.link_posts_processed,
                                        response.link_posts_to_process_diff,
                                        response.meta_filled,
                                        response.links_filled,
                                        response.loops_unchanged);
                    
                    // if the meta has been processed
                    if(response.meta_filled){
                        // update the loading bar
                        animateTheReportLoadingBar(response, '.wpil-wizard-post-progress-loader', response.processing_complete);
                    }
                }
            }
        });
    }

    /**
     * Updates the loading bar length and the displayed completion status.
     * 
     * A possible improvement might be to progressively update the loading bar so its more interesting.
     * As it is now, the bar jumps every 60s, so it might be a bit dull and the user might wonder if it's working.
     **/
    function animateTheReportLoadingBar(response, targetSelector = '', complete = false){
        // get the loading display
        var loadingDisplay = $('#wpbody-content ' + targetSelector);
        // create some variable to update the display with
        var percentCompleted = Math.floor((response.link_posts_processed/response.link_posts_to_process_count) * 100);
        //var displayedStatus = percentCompleted + '%' + ((response.links_filled) ? (', ' + response.link_posts_processed + '/' + response.link_posts_to_process_count) : '') + ' ' + wpil_ajax.completed;
        var displayedStatus = percentCompleted + '%';

        if(complete){
            displayedStatus = '100%';
            percentCompleted = 100;
        }

        // update the display with the new info
        loadingDisplay.find('.wpil-loading-status').text(displayedStatus);
        loadingDisplay.find('.progress_count').css({'width': percentCompleted + '%'});
    }

    function wpil_report_next_step()
    {
        location.reload();
    }

    /**
     * Keeps track of the loop's progress in a global context so the scan is less susceptible to minor errors like timeouts
     **/
    var globalKeywordScan = {
        'count': 0,
        'total': 0,
        'error_count': 0
    };

    function wpil_target_keyword_reset_process(count, total, reset = false) {
        globalKeywordScan['count'] = count;
        globalKeywordScan['total'] = total;

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'wpil_target_keyword_reset',
                nonce: $('.wpil-wizard-reset-target-keyword-nonce').val(),
                count: count,
                total: total,
                reset: reset,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                globalKeywordScan.error_count += 1;

                // if the scan has errored less than 5 times, try it again
                if(globalKeywordScan.error_count < 5){
                    wpil_target_keyword_reset_process(
                        globalKeywordScan.count,
                        globalKeywordScan.total
                    );
                }else{
                    var wrapper = document.createElement('div');
                    $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                    $(wrapper).append(jqXHR.responseText);
                    wpil_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(function(){
                        //location.reload();
                    });
                }
            },
            success: function(response){
                console.log(response);

                if(!isJSON(response)){
                    response = extractAndValidateJSON(response, ['error', 'state', 'keywords_found', 'count', 'total', 'finish', 'estimate']);
                }

                if (response.error) {
                    wpil_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                var completion = Math.round(response.estimate.completed/response.estimate.total * 100);
                $('.wpil-wizard-target-keyword-progress-loader .progress_count').css({'width': completion + '%'});
                $('.wpil-wizard-target-keyword-progress-loader .progress_count').html(completion + '%');


                if (response.finish) {
                    // update the status to show we're done
                    $('.wpil-wizard-target-keyword-progress-loader .progress_count').css({'width': '100%'});
                    $('.wpil-wizard-target-keyword-progress-loader .progress_count').html('100%');
                    
                    // note the success in the status object
                    processingStatus['runKeywordScan'] = true;

                    // ping the checker to see if we should redirect now
                    checkInstallationComplete();
                } else {
                    wpil_target_keyword_reset_process(response.count, response.total)
                }
            }
        });
    }

    function cycleFunFacts(){
        var visibleFact = jQuery('.wpil-wizard-fun-fact:visible'),
            id = parseInt(visibleFact.data('wpil-wizard-fun-fact-id')),
            id = (id == 31) ? 1: (id + 1);

        visibleFact.fadeOut(750, function(){
            setTimeout(function(){
                jQuery('.wpil-wizard-fun-fact[data-wpil-wizard-fun-fact-id="' + id + '"]').fadeIn(750);
            }, 200);
        });
    }

    function setCompletionFlag(){
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'wpil_wizard_set_completion_flag'
            },
            success: function(response){
                console.log(response);
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
        });
    }

})(jQuery);