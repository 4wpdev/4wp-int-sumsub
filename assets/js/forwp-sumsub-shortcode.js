jQuery(document).ready(function($) {
    
    // Initialize on page load
    initVisibleSumSubContainers();
    
    // Watch for dynamic content changes (multi-step forms)
    var observer = new MutationObserver(function(mutations) {
        var shouldCheck = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                shouldCheck = true;
            }
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                shouldCheck = true;
            }
        });
        
        if (shouldCheck) {
            initVisibleSumSubContainers();
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style', 'class']
    });
    
    function initVisibleSumSubContainers() {
        $('.sumsub-websdk-container:not(.sumsub-initialized)').each(function() {
            var $container = $(this);
            
            if (isContainerVisible($container)) {
                $container.addClass('sumsub-initialized');
                
                var $form = $container.closest('form');
                var $submitBtn = $form.find('input[type="submit"], button[type="submit"], .gform_next_button');
                var containerId = $container.attr('id');
                
                $container.show();
                $submitBtn.prop('disabled', true).addClass('sumsub-disabled').attr('aria-disabled', 'true');
                
                if ($container.siblings('.sumsub-info-msg').length === 0) {
                    $container.before('<div class="sumsub-info-msg" role="status" aria-live="polite">' + sumsubVars.verification_required + '</div>');
                }
                
                initSumSubSDK($container, $form, $submitBtn, containerId);
            }
        });
    }
    
    function isContainerVisible($container) {
        var $gformPage = $container.closest('.gform_page');
        
        if ($gformPage.length > 0) {
            // Multi-step форма - перевіряємо чи крок видимий
            return $gformPage.is(':visible') && $gformPage.css('display') !== 'none';
        }
        
        // Single-step форма - перевіряємо чи форма видима
        var $form = $container.closest('form');
        return $form.length > 0 && $form.is(':visible');
    }
    
    function initSumSubSDK($container, $form, $submitBtn, containerId) {
        // Add security nonce to request
        var requestData = {
            action: 'forwp_sumsub_get_token',
            nonce: sumsubVars.nonce
        };
        
        fetch(sumsubVars.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(requestData)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showNotification(data.data.error || sumsubVars.sdk_error, 'error');
                return;
            }
            
            if (!window.snsWebSdk) {
                console.error('SumSub SDK not loaded');
                showNotification(sumsubVars.sdk_not_loaded, 'error');
                return;
            }
            
            const sdk = window.snsWebSdk.init(
                data.data.token,
                () => fetch(sumsubVars.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(requestData)
                }).then(r => r.json()).then(j => j.data.token)
            )
            .withConf({ lang: sumsubVars.lang || 'en' })
            .withOptions({ adaptIframeHeight: true, addViewportTag: false })
            .on('idCheck.onApplicantStatusChanged', function(payload) {
                if (payload.reviewStatus === 'completed') {
                    handleVerificationSuccess(payload, $container, $form, $submitBtn, sdk);
                }
            })
            .on('idCheck.onError', function(err) {
                console.error('SumSub verification error:', err);
                showNotification(sumsubVars.verification_error, 'error');
                resetFormState($container, $submitBtn);
            })
            .build();
            
            sdk.launch('#' + containerId);
        })
        .catch(function(err) {
            console.error('SumSub SDK initialization error:', err);
            showNotification(sumsubVars.sdk_error, 'error');
            resetFormState($container, $submitBtn);
        });
    }
    
    function handleVerificationSuccess(payload, $container, $form, $submitBtn, sdk) {
        window.sumsubSdkPayload = payload;
        sdk.destroy();
        
        // Update form fields
        $form.find('input[name="sumsub_applicant_id"]').val(payload.applicantId || '');
        $form.find('input[name="sumsub_verified"]').val('on');
        
        // Update UI
        $container.hide();
        $container.siblings('.sumsub-info-msg').remove();
        
        if ($container.siblings('.sumsub-success-msg').length === 0) {
            $container.after('<div class="sumsub-success-msg" role="status" aria-live="polite">✅ ' + sumsubVars.success + '</div>');
        }
        
        // Enable form submission
        $submitBtn.prop('disabled', false).removeClass('sumsub-disabled').removeAttr('aria-disabled');
    }
    
    function resetFormState($container, $submitBtn) {
        $container.hide();
        $submitBtn.prop('disabled', false).removeClass('sumsub-disabled').removeAttr('aria-disabled');
    }
    
    function showNotification(message, type) {
        // Remove existing notifications
        $('.sumsub-notification').remove();
        
        var typeClass = type === 'error' ? 'sumsub-error-msg' : 'sumsub-info-msg';
        var icon = type === 'error' ? '❌' : 'ℹ️';
        
        var $notification = $('<div class="sumsub-notification ' + typeClass + '" role="alert" aria-live="assertive">' + 
                            icon + ' ' + message + '</div>');
        
        // Add to top of page or near SumSub container
        var $target = $('.sumsub-websdk-container').first();
        if ($target.length > 0) {
            $target.before($notification);
        } else {
            $('body').prepend($notification);
        }
        
        // Auto-remove after 5 seconds for non-error messages
        if (type !== 'error') {
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
});