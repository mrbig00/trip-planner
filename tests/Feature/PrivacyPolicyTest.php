<?php

test('guests can view the privacy policy', function () {
    $response = $this->get(route('privacy'));

    $response->assertStatus(200);
    $response->assertSee('Privacy Policy', false);
});
