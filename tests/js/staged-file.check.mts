/**
 * Run with:  node --experimental-strip-types --test tests/js/staged-file.check.mts
 *
 * The repo has no JS test runner (deliberately), so this uses node's own
 * runner and type stripping. It lives outside resources/js so tsc/eslint do
 * not try to compile it — it only guards the annotation naming that decides
 * what an edited upload is called.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import {
    annotatedFileName,
    blobToFile,
    isVideoFile,
} from '../../resources/js/features/media-edit/utils/staged-file.ts';

test('annotatedFileName keeps the extension and never eats the name', () => {
    assert.equal(annotatedFileName('site-photo.jpg'), 'site-photo-annotated.jpg');
    assert.equal(annotatedFileName('SCAN.PNG'), 'SCAN-annotated.PNG');
    assert.equal(annotatedFileName('no-extension'), 'no-extension-annotated');
    assert.equal(annotatedFileName('.hidden'), '.hidden-annotated');
});

test('isVideoFile trusts the MIME type, then falls back to the extension', () => {
    assert.equal(isVideoFile(new File([], 'clip.mov', { type: 'video/quicktime' })), true);
    // Android camera video often arrives with an empty type.
    assert.equal(isVideoFile(new File([], 'clip.mov')), true);
    assert.equal(isVideoFile(new File([], 'photo.jpg')), false);
    assert.equal(isVideoFile(new File([], 'photo.jpg', { type: 'image/jpeg' })), false);
});

test('blobToFile carries the blob type and falls back to jpeg', () => {
    const png = blobToFile(new Blob(['x'], { type: 'image/png' }), 'a.png');
    assert.equal(png.type, 'image/png');
    assert.equal(png.name, 'a.png');

    const untyped = blobToFile(new Blob(['x']), 'b.jpg');
    assert.equal(untyped.type, 'image/jpeg');
});
