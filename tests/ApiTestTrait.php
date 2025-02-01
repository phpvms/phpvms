<?php

trait ApiTestTrait
{
    public function assertApiResponse(array $actualData): void
    {
        $this->assertApiSuccess();

        $response = json_decode($this->response->getContent(), true);
        $responseData = $response['data'];

        $this->assertNotEmpty($responseData['id']);
        $this->assertModelData($actualData, $responseData);
    }

    public function assertApiSuccess(): void
    {
        $this->assertResponseOk();
        $this->seeJson(['success' => true]);
    }

    public function assertModelData(array $actualData, array $expectedData): void
    {
        foreach (array_keys($actualData) as $key) {
            $this->assertEquals($actualData[$key], $expectedData[$key]);
        }
    }
}
