import assert from 'node:assert/strict';

function commandSpec(name, args, platform = process.platform, env = process.env) {
    if (platform === 'win32' && name === 'npm') {
        const comspec = env.ComSpec || env.COMSPEC || 'cmd.exe';
        return {
            file: comspec,
            args: ['/d', '/s', '/c', ['npm', ...args].join(' ')],
        };
    }

    return { file: name, args };
}

const windowsNpm = commandSpec('npm', ['run', 'build'], 'win32', { ComSpec: 'C:\\Windows\\System32\\cmd.exe' });
assert.equal(windowsNpm.file, 'C:\\Windows\\System32\\cmd.exe');
assert.deepEqual(windowsNpm.args, ['/d', '/s', '/c', 'npm run build']);
assert.notEqual(windowsNpm.file.toLowerCase(), 'npm.cmd');

const windowsPhp = commandSpec('php', ['artisan', 'test'], 'win32', {});
assert.equal(windowsPhp.file, 'php');
assert.deepEqual(windowsPhp.args, ['artisan', 'test']);

const posixNpm = commandSpec('npm', ['run', 'build'], 'linux', {});
assert.equal(posixNpm.file, 'npm');
assert.deepEqual(posixNpm.args, ['run', 'build']);

console.log('Runtime repair command-runner self-test passed.');
