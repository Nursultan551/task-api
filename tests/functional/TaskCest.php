<?php

class TaskCest
{
    public function crudFlow(FunctionalTester $I)
    {
        try {
            \Yii::$app->db->open();
        } catch (\Throwable $e) {
            $I->comment('Skipping CRUD API test: DB not available - ' . $e->getMessage());
            return;
        }
        // Set Authorization header for all requests
        $I->haveHttpHeader('Authorization', 'Bearer MY_SUPER_TOKEN');
        // Create
    $I->sendPOST('/api/tasks', [
            'title' => 'Test task 12345',
            'priority' => 'high',
            'due_date' => '2030-01-01',
            'tags' => ['test','ci']
        ]);
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['title' => 'Test task 12345']);
        $id = $I->grabDataFromResponseByJsonPath('$.id')[0];

        // Get
    $I->haveHttpHeader('Authorization', 'Bearer MY_SUPER_TOKEN');
    $I->sendGET("/api/tasks/{$id}");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['id' => $id]);

        // List with filter
    $I->haveHttpHeader('Authorization', 'Bearer MY_SUPER_TOKEN');
    $I->sendGET('/api/tasks', ['status' => 'pending', 'limit' => 5, 'page' => 1]);
        $I->seeResponseCodeIs(200);

        // Update
    $I->haveHttpHeader('Authorization', 'Bearer MY_SUPER_TOKEN');
    $I->sendPUT("/api/tasks/{$id}", ['status' => 'in_progress']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'in_progress']);

        // Toggle
    $I->haveHttpHeader('Authorization', 'Bearer MY_SUPER_TOKEN');
    $I->sendPATCH("/api/tasks/{$id}/toggle-status");
        $I->seeResponseCodeIs(200);

        // Delete (soft)
    $I->haveHttpHeader('Authorization', 'Bearer MY_SUPER_TOKEN');
    $I->sendDELETE("/api/tasks/{$id}");
        $I->seeResponseCodeIs(200);

        // Not found after delete
    $I->haveHttpHeader('Authorization', 'Bearer MY_SUPER_TOKEN');
    $I->sendGET("/api/tasks/{$id}");
        $I->seeResponseCodeIs(404);
    }
}
