import assert from 'node:assert/strict';
import {pathToFileURL} from 'node:url';
import path from 'node:path';

const dashboardModule = path.resolve(process.cwd(), 'resources/js/admin-console/dashboard.js');
const {dashboardViewModel, normalizeDashboard, resolveDashboardValue} = await import(pathToFileURL(dashboardModule).href);

const payload = {
    data: {
        snapshot: {
            business: {
                users: {total: 31, new_today: 2, dau: 9, wau: 17, mau: 24, online_now: 4},
                devices: {active: 11},
                circles: {active: 7, created_today: 1},
                engagement: {messages_routed: 5, moments_created: 3, pings_sent: 6},
            },
            operations: {
                safety: {active_sos: 2, sos_today: 2},
                moderation: {pending_reports: 4, backlog: 4},
                support: {backlog: 3},
                health: {
                    api: {status: 'operational'},
                    websocket: {status: 'operational'},
                    queues: {status: 'healthy'},
                    providers: {status: 'operational'},
                    storage: {status: 'healthy'},
                },
            },
            environment: {name: 'testing'},
            generated_at: '2026-09-03T00:00:00Z',
        },
    },
};

const snapshot = normalizeDashboard(payload);
assert.equal(snapshot.business.users.total, 31, 'canonical data.snapshot must be selected');
assert.equal(resolveDashboardValue(snapshot, ['users.total']), 31, 'business namespace fallback must resolve users.total');
assert.equal(resolveDashboardValue(snapshot, ['safety.active_sos']), 2, 'operations namespace suffix must resolve safety data');
assert.equal(resolveDashboardValue(snapshot, ['health.api.status']), 'operational', 'operations health namespace must resolve');

const view = dashboardViewModel(payload);
assert.equal(view.metrics['Total users'], 31);
assert.equal(view.metrics['New users today'], 2);
assert.equal(view.metrics['Daily active users'], 9);
assert.equal(view.metrics['Monthly active users'], 24);
assert.equal(view.metrics['Online users'], 4);
assert.equal(view.metrics['Active devices'], 11);
assert.equal(view.metrics['Active Circles'], 7);
assert.equal(view.metrics['Active SOS'], 2);
assert.equal(view.engagement.WAU, 17);
assert.equal(view.engagement['Messages routed'], 5);
assert.equal(view.safety['Moderation backlog'], 4);
assert.equal(view.safety['Support backlog'], 3);
assert.equal(view.health.API, 'operational');

assert.throws(
    () => normalizeDashboard({data: {snapshot: {business: {users: {}}}}}),
    /incompatible with this Admin Console build/,
    'contract drift must fail visibly rather than rendering an all-dash dashboard',
);

console.log('Dashboard response-contract adapter passed.');
