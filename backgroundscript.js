/**
 * Copyright (C) FastGram 2016-2023 - All Rights Reserved
 *
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by FastGram <@>, 2016-2023
 */

// Panel API Configuration - loaded from config.json
let PANEL_API = {
    baseUrl: 'http://localhost/painel_gerenciador' // default fallback
};

// Cache for profile verification
let profileCache = {
    username: null,
    isAuthorized: false,
    timestamp: 0
};
let CACHE_DURATION = 5 * 60 * 1000; // default 5 minutes in milliseconds

// Load configuration from config.json
async function loadConfig() {
    try {
        const response = await fetch(chrome.runtime.getURL('config.json'));
        const config = await response.json();

        if (config.panel_url) {
            PANEL_API.baseUrl = config.panel_url;
        }
        if (config.cache_duration_minutes) {
            CACHE_DURATION = config.cache_duration_minutes * 60 * 1000;
        }

        console.log('Config loaded:', PANEL_API.baseUrl);
        return config;
    } catch (error) {
        console.error('Error loading config.json:', error);
        return null;
    }
}

// Initialize config on startup
loadConfig();

// Array of random comments for stories
var storyComments = [
    "😍", "🔥", "👏", "❤️", "😊", "👍", "🙌", "💯", "✨", "😎",
    "Incrível!", "Amazing!", "Love it!", "Perfect!", "Wow!", "Nice!",
    "❤️❤️", "🔥🔥", "👏👏", "😍😍", "Top!", "Show!", "💙", "💚"
];

function getRandomComment() {
    return storyComments[Math.floor(Math.random() * storyComments.length)];
}

// Get random delay with variation
function getRandomDelay(baseDelay, variation) {
    var minDelay = baseDelay * (1 - variation);
    var maxDelay = baseDelay * (1 + variation);
    return Math.floor(Math.random() * (maxDelay - minDelay + 1)) + minDelay;
}

// Base delays for stories (increased to be more human-like)
var STORY_VIEW_TIME = 13000; // 13 seconds base per story
var STORY_VARIATION = 0.4; // ±40% variation
var ACTION_DELAY = 4000; // Extra time for likes/comments

chrome.runtime.onMessage.addListener(function (request, sender, sendResponse) {

    if (request.openReelTab) {

        var shortcode = request.openReelTab.code || request.openReelTab.shortcode;

        chrome.tabs.create({
            url: "https://www.instagram.com/p/" + shortcode
        }, function (tab) {


            var tabId = tab.id;

            chrome.tabs.onUpdated.addListener(function (tabId, info) {
                if (info.status === 'complete') {
                    chrome.tabs.sendMessage(tabId, {
                        hideGrowbot: true
                    });

                    setTimeout(function () {
                        chrome.tabs.sendMessage(tabId, {
                            hideGrowbot: true
                        });
                    }, 3000);


                    if (request.openReelTab.LikeWhenWatchingReel == true) {
                        setTimeout(function () {
                            // click Like
                            chrome.tabs.sendMessage(tabId, {
                                clickSomething: 'svg[aria-label="Like"][width="24"]',
                                parent: 'div[role="button"]'

                            });
                        }, (((request.openReelTab.video_duration || 20) * 750)));
                    }


                    if (request.openReelTab.SaveWhenWatchingReel == true) {
                        setTimeout(function () {
                            // click Save
                            chrome.tabs.sendMessage(tabId, {
                                clickSomething: 'svg[aria-label="Save"]',
                                parent: 'div[role="button"]'
                            });
                        }, (((request.openReelTab.video_duration || 20) * 750) + 2000));
                    }


                    setTimeout(function () {
                        chrome.tabs.remove(tab.id);
                    }, (((request.openReelTab.video_duration || 20) * 1000) + 1000));
                }
            });



        });

    }

    if (request.openStoryTab) {
        var username = request.openStoryTab.username;
        var itemCount = request.openStoryTab.itemCount || 1;
        var isSingleStory = request.openStoryTab.isSingleStory;
        var action = request.openStoryTab.action || 'view';
        var storyDuration = itemCount * getRandomDelay(STORY_VIEW_TIME, STORY_VARIATION); // Random 8-18 seconds per story

        chrome.tabs.create({
            url: "https://www.instagram.com/stories/" + username + "/"
        }, function (tab) {
            var tabId = tab.id;

            chrome.tabs.onUpdated.addListener(function (tabId, info) {
                if (info.status === 'complete') {
                    // Hide the extension UI on story page
                    chrome.tabs.sendMessage(tabId, {
                        hideGrowbot: true
                    });

                    setTimeout(function () {
                        chrome.tabs.sendMessage(tabId, {
                            hideGrowbot: true
                        });
                    }, 3000);

                    // Different behavior for single vs multiple stories
                    if (isSingleStory) {
                        // For single story, try to click "Ver story" button first
                        setTimeout(function() {
                            chrome.tabs.sendMessage(tabId, {
                                clickSomething: 'button:contains("Ver story"), button:contains("View story"), button:contains("Ver historia"), div[role="button"]:contains("Ver story"), div[role="button"]:contains("View story")'
                            });
                            
                            // Backup: try clicking on the story area
                            setTimeout(function() {
                                chrome.tabs.sendMessage(tabId, {
                                    clickSomething: 'div[role="button"][tabindex="0"], section img, canvas'
                                });
                            }, 2000);
                        }, 3000); // Wait 3 seconds for page load
                        
                        // Perform action on single story
                        if (action === 'like') {
                            setTimeout(function() {
                                chrome.tabs.sendMessage(tabId, {
                                    clickSomething: 'button[aria-label="Curtir"], button[aria-label="Like"], svg[aria-label="Like"], svg[aria-label="Curtir"], div[role="button"] svg[aria-label="Like"], div[role="button"] svg[aria-label="Curtir"], span[aria-label="Like"], span[aria-label="Curtir"]',
                                    verifyExists: true
                                });
                            }, getRandomDelay(6000, 0.3)); // Wait 4.2-7.8 seconds to like
                        } else if (action === 'comment') {
                            setTimeout(function() {
                                chrome.tabs.sendMessage(tabId, {
                                    clickSomething: 'button[aria-label="Responder"], button[aria-label="Reply"], svg[aria-label="Reply"], svg[aria-label="Responder"], div[role="button"] svg[aria-label="Reply"], div[role="button"] svg[aria-label="Responder"], span[aria-label="Reply"], span[aria-label="Responder"]',
                                    verifyExists: true
                                });
                                
                                setTimeout(function() {
                                    chrome.tabs.sendMessage(tabId, {
                                        sendComment: getRandomComment(),
                                        verifyCommentField: true
                                    });
                                }, getRandomDelay(2500, 0.3));
                            }, getRandomDelay(6000, 0.3)); // Wait 4.2-7.8 seconds to comment
                        }
                        
                        // Close after single story duration
                        setTimeout(function () {
                            chrome.tabs.remove(tab.id);
                        }, action !== 'view' ? getRandomDelay(18000, 0.3) : getRandomDelay(12000, 0.3)); // Random timing for actions
                    } else {
                        // For multiple stories, use the existing navigation system
                        var clickInterval;
                        var currentStoryIndex = 0;
                        
                        // Wait a bit for stories to load, then start auto-advancing
                        setTimeout(function() {
                            // Try multiple approaches to advance stories
                            clickInterval = setInterval(function() {
                                currentStoryIndex++;
                                
                                // Perform actions on specific story
                                if (action === 'like') {
                                    // Like every story with better selectors
                                    chrome.tabs.sendMessage(tabId, {
                                        clickSomething: 'button[aria-label="Curtir"], button[aria-label="Like"], svg[aria-label="Like"], svg[aria-label="Curtir"], div[role="button"] svg[aria-label="Like"], div[role="button"] svg[aria-label="Curtir"], span[aria-label="Like"], span[aria-label="Curtir"], div[data-testid="like-button"]',
                                        verifyExists: true
                                    });
                                } else if (action === 'comment' && Math.random() < 0.3) {
                                    // Comment randomly on 30% of stories with better selectors
                                    chrome.tabs.sendMessage(tabId, {
                                        clickSomething: 'button[aria-label="Responder"], button[aria-label="Reply"], svg[aria-label="Reply"], svg[aria-label="Responder"], div[role="button"] svg[aria-label="Reply"], div[role="button"] svg[aria-label="Responder"], span[aria-label="Reply"], span[aria-label="Responder"]',
                                        verifyExists: true
                                    });
                                    
                                    setTimeout(function() {
                                        chrome.tabs.sendMessage(tabId, {
                                            sendComment: getRandomComment(),
                                            verifyCommentField: true
                                        });
                                    }, getRandomDelay(2500, 0.3));
                                }
                                
                                // Navigate to next story
                                setTimeout(function() {
                                    chrome.tabs.sendMessage(tabId, {
                                        clickSomething: 'button[aria-label="Next"], button[aria-label="Próximo"], button[aria-label="Siguiente"], div[role="button"] svg[aria-label="Next story"], div[data-testid="keyboard-right-arrow"], div._ac0a'
                                    });
                                    
                                    // Also simulate Right arrow key press as backup
                                    setTimeout(function() {
                                        chrome.tabs.sendMessage(tabId, {
                                            keyPress: 'ArrowRight'
                                        });
                                    }, 1000);
                                }, action !== 'view' ? getRandomDelay(3000, 0.3) : getRandomDelay(1000, 0.2)); // Random delay for actions
                            }, getRandomDelay(STORY_VIEW_TIME, STORY_VARIATION)); // Random 8-18 seconds per story
                        }, getRandomDelay(5000, 0.2)); // Wait 4-6 seconds for initial load

                        // Close tab after all stories are viewed
                        var totalDuration = storyDuration + (action !== 'view' ? getRandomDelay(ACTION_DELAY * 2, 0.3) : getRandomDelay(ACTION_DELAY, 0.3));
                        setTimeout(function () {
                            if (clickInterval) clearInterval(clickInterval);
                            chrome.tabs.remove(tab.id);
                        }, totalDuration);
                    }
                }
            });
        });
    }

    if (request.updatewanted && request.updatewanted == true) {
        gblIgBotUser.init();
    }

    if (request.guidCookie) {
        gblIgBotUser.overrideGuid(request.guidCookie);
    }

    if (request.ftOver == "true") {
        gblIgBotUser.overrideFT();
    }


    if (request.ig_user) {
        gblIgBotUser.ig_users.push(request.ig_user);
        gblIgBotUser.ig_users = uniq(gblIgBotUser.ig_users);
        gblIgBotUser.current_ig_username = request.ig_user.username;

        if (request.ig_user_account_stats) {
            gblIgBotUser.account_growth_stats.push(request.ig_user_account_stats);
            gblIgBotUser.account_growth_stats = uniq(gblIgBotUser.account_growth_stats);
        }

        checkInstallDate();

        gblIgBotUser.saveToLocal();
        gblIgBotUser.saveToServer();

        // Verify profile when user is detected
        verifyInstagramProfile(request.ig_user.username);
    }

    if (request.fnc == 'openBuyScreen') {
        openBuyScreen();
    }

    if (request.fnc == 'verifyProfile') {
        verifyInstagramProfile(request.username).then(function(result) {
            sendResponse(result);
        });
        return true; // Keep the message channel open for async response
    }

    if (request.fnc == 'clearCacheAndVerify') {
        clearProfileCache();
        verifyInstagramProfile(request.username).then(function(result) {
            sendResponse(result);
        });
        return true; // Keep the message channel open for async response
    }

    sendResponse();

});

var gblIgBotUser = {
    user_guid: undefined,
    install_date: new Date().toUTCString(),
    instabot_install_date: undefined,
    ig_users: [],
    licenses: {},
    actions: [{
        date: '',
        action: ''
    }],
    account_growth_stats: [],
    options: {},
    //      whitelist: [],
    //      savedQueue: [{ name: 'q1',date:datetime,queue:[]},{ name: 'q1',date:datetime,queue:[]}]
    init: async function () {

        runWinVarsScript();

        this.user_guid = await this.getPref('growbot_user_guid');

        if (!this.user_guid || this.user_guid == false) {
            this.user_guid = this.uuidGenerator();
            this.setPref('growbot_user_guid', this.user_guid);
        }

        // Profile verification will happen when ig_user is detected from content script

    },
    overrideGuid: function (newGuid) {
        this.user_guid = newGuid;
        this.setPref('growbot_user_guid', this.user_guid);
    },
    overrideFT: function () {
        // Trial system removed - just show activation screen
        openBuyScreen();
    },
    uuidGenerator: function () {
        var S4 = function () {
            return (((1 + Math.random()) * 0x10000) | 0).toString(16).substring(1);
        };
        return (S4() + S4() + "-" + S4() + "-" + S4() + "-" + S4() + "-" + S4() + S4() + S4());
    },
    getPref: async function (name) {
        return new Promise(function (resolve) {
            chrome.storage.local.get(name, function (value) {
                if (Object.keys(value).length > 0) {
                    resolve(value[name]);
                } else {
                    resolve(false);
                }
            });
        });
    },
    setPref: async function (name, value) {
        chrome.storage.local.set({
            [name]: value
        }, function () { });
    },
    saveToLocal: function () {
        chrome.storage.local.set({
            'igBotUser': JSON.stringify(gblIgBotUser)
        }, function () { });
    },
    saveToServer: function () {
        // Removed old API call - license verification now uses new system
    }
};


// Trial time removed - license system now uses new API
var first_run = false;
var todaysdate = new Date();
var today = todaysdate.getTime();
var timeSinceInstall;

chrome.action.onClicked.addListener(function (tab) {
    chrome.tabs.query({
        url: ["https://www.instagram.com/", "https://www.instagram.com/*"],
        currentWindow: true
    }, tabs => {
        if (tabs.length === 0) {
            chrome.tabs.create({
                url: 'https://www.instagram.com/'
            }, function (tab) {
                chrome.tabs.sendMessage(tab.id, {
                    "openGrowbot": true,
                    igBotUser: gblIgBotUser
                });
            });
        } else {
            var toggled = false;
            for (var i = 0; i < tabs.length; i++) {
                if (tabs[i].active === true) {
                    toggled = true;
                    chrome.tabs.sendMessage(tabs[i].id, {
                        "toggleGrowbot": true,
                        igBotUser: gblIgBotUser
                    });
                }
            }
            if (toggled === false) {
                // only runs if instagram wasn't the active tab:
                chrome.tabs.update(tabs[0].id, {
                    active: true
                });
                chrome.tabs.sendMessage(tabs[0].id, {
                    "openGrowbot": true,
                    igBotUser: gblIgBotUser
                });
            }
        }
    });
});


chrome.runtime.onInstalled.addListener(installedOrUpdated);

function installedOrUpdated() {
    gblIgBotUser.init();

    chrome.tabs.create({
        url: "https://www.instagram.com"
    }, function (tab) {

        setTimeout(function () {
            sendMessageToInstagramTabs({
                "extension_updated": true
            });
        }, 5000);

    });
}

function runWinVarsScript() {
    chrome.tabs.query({
        url: ["https://www.instagram.com/*", "https://www.instagram.com/"]
    }, tabs => {
        for (var i = 0; i < tabs.length; i++) {
            var igTabId = tabs[i].id;
            chrome.scripting.executeScript({
                target: {
                    tabId: igTabId
                },
                files: ['winvars.js'],
                world: 'MAIN'
            },
                function () { });
        }
    });
}


async function checkInstallDate() {

    var installDate = await gblIgBotUser.getPref('instabot_install_date');
    console.log(new Date(+installDate).toUTCString())

    if (installDate == false) {
        first_run = true;
        installDate = '' + today;
        gblIgBotUser.setPref('instabot_install_date', installDate);
    }

    gblIgBotUser.instabot_install_date = installDate;

    // string -> int -> date -> UTCString for python
    gblIgBotUser.install_date = new Date(+installDate).toUTCString();
    timeSinceInstall = today - installDate;

    // Profile verification happens when ig_user is detected

}

function sendMessageToInstagramTabs(message) {
    chrome.tabs.query({
        url: ["https://www.instagram.com/", "https://www.instagram.com/*"]
    }, function (tabs) {
        //if (tabs.length == 0) return false;
        for (var i = 0; i < tabs.length; i++) {
            chrome.tabs.sendMessage(tabs[i].id, message).then(response => {
                // console.log("Message from the content script:");
                // console.log(response.response);
            }).catch(function () {
                // console.log('error when: ' + message);
                // console.log(message);
            });
        }
    });
}


function onError(error) {
    //console.error(`Error: ${error}`);
}

// Verify Instagram profile with panel
async function verifyInstagramProfile(username) {
    if (!username) {
        console.log('No username provided for verification');
        showConnectionError('Usuário não detectado. Faça login no Instagram.');
        return { success: false, authorized: false, error: 'no_username' };
    }

    // Store current username for UI
    gblIgBotUser.current_ig_username = username;

    // Check cache first (only if authorized - never cache failures)
    const now = Date.now();
    if (profileCache.username === username &&
        profileCache.isAuthorized === true &&
        profileCache.timestamp > 0 &&
        (now - profileCache.timestamp) < CACHE_DURATION) {
        console.log('Using cached profile verification for:', username);
        allLicensesFetched(1, { "profile_authorized": 1 });
        return { success: true, authorized: true };
    }

    try {
        console.log('Verifying profile with panel:', username);
        const response = await fetch(`${PANEL_API.baseUrl}/index.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ig_username: username })
        });

        // Check if response is OK
        if (!response.ok) {
            console.error('Server returned error:', response.status);
            showConnectionError('Erro no servidor de validação (HTTP ' + response.status + ')');
            return { success: false, authorized: false, error: 'server_error' };
        }

        const result = await response.text();
        const isAuthorized = result.trim() === '1';

        console.log('Profile verification response:', result, 'Authorized:', isAuthorized);

        if (isAuthorized) {
            // Update cache only for authorized users
            profileCache = {
                username: username,
                isAuthorized: true,
                timestamp: now
            };
            allLicensesFetched(1, { "profile_authorized": 1 });
            return { success: true, authorized: true };
        } else {
            // Not authorized - show activation screen
            clearProfileCache();
            openBuyScreen();
            return { success: true, authorized: false };
        }
    } catch (error) {
        console.error('Profile verification error:', error);
        // Connection error - block extension and show error
        clearProfileCache();
        showConnectionError('Sem conexão com o servidor de validação. Verifique sua internet.');
        return { success: false, authorized: false, error: 'connection_error' };
    }
}

// Show connection error screen (different from unauthorized)
function showConnectionError(message) {
    sendMessageToInstagramTabs({
        "showConnectionError": true,
        "errorMessage": message,
        "current_ig_username": gblIgBotUser.current_ig_username,
        igBotUser: gblIgBotUser
    });
}

// Force re-verification (clear cache)
function clearProfileCache() {
    profileCache = {
        username: null,
        isAuthorized: false,
        timestamp: 0
    };
}

function allLicensesFetched(count, licenses) {
    if (count === 1) {
        // Profile authorized
        sendMessageToInstagramTabs({
            "instabot_install_date": gblIgBotUser.instabot_install_date,
            "instabot_has_license": true,
            "current_ig_username": gblIgBotUser.current_ig_username,
            igBotUser: gblIgBotUser
        });
    }
    // Note: openBuyScreen is called directly from verifyInstagramProfile when not authorized

    gblIgBotUser.licenses = licenses;

    gblIgBotUser.saveToLocal();
}


function openBuyScreen() {
    //console.log(gblIgBotUser);
    sendMessageToInstagramTabs({
        "openBuyScreen": true,
        "current_ig_username": gblIgBotUser.current_ig_username,
        igBotUser: gblIgBotUser
    });
}


function uniq(ar) {
    return Array.from(new Set(ar.map(JSON.stringify))).map(JSON.parse);
}

// Initialize on service worker startup
gblIgBotUser.init();
