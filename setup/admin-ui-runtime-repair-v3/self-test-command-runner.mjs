import assert from 'node:assert/strict';

function commandSpec(name, args, platform = process.platform, env = process.env) {
    if (platform === 'win32' && name === 'npm') {
        return { file: env.ComSpec || env.COMSPEC || 'cmd.exe', args: ['/d', '/s', '/c', ['npm', ...args].join(' ')] };
    }
    return { file: name, args };
}

const windows = commandSpec('npm', ['run', 'build'], 'win32', { ComSpec: 'C:\\Windows\\System32\\cmd.exe' });
assert.equal(windows.file, 'C:\\Windows\\System32\\cmd.exe');
assert.deepEqual(windows.args, ['/d', '/s', '/c', 'npm run build']);
assert.deepEqual(commandSpec('php', ['artisan'], 'win32', {}), { file: 'php', args: ['artisan'] });
console.log('Windows-safe command runner self-test passed.');
