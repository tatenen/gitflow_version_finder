<?php

/**
 * Class GitFlowVersionFinder
 */
class GitFlowVersionFinder
{
    /**
     * @param bool $useDescribe для ветки master должно быть равно true
     * @return mixed|null|string
     */
	public function findVersion($useDescribe = true)
	{
		$version = $useDescribe ? $this->_findVersionWithDescribe() : $this->_findVersionOther();

		return $version;
	}

	/**
	 * Ищет ближайший тег через комаду git describe.
	 * Идеальный вариант для ветки master
	 *
	 * @return null|string
	 */
	protected function _findVersionWithDescribe()
	{
		$command = 'git describe --tags 2>&1';

		exec($command, $output, $return);

		if ($return === 0) {
			$detail = explode('-', $output[0]);
			$version = $detail[0];

			return $version;
		}

		return null;
	}

	/**
	 * Поиск тега для любой ветки != master
	 * todo разбить по методам + убрать дебаг
	 *
	 * @return mixed
	 */
	protected function _findVersionOther()
	{
		exec('git rev-parse HEAD ', $output, $return);
		$currentSha = $output[0];
		unset($output);

		exec('git tag --contains ' . $currentSha, $output, $return);

		$upperVersion = null;

		if ($output) {
			$upperVersion = $output[0];
			unset($output);

			$command = "git show --pretty=%H $upperVersion";
			exec($command, $output, $return);
			$versionSha = $output[0];

			unset($output);

			if ($versionSha == $currentSha) {
				return $upperVersion;
			}

		}

		unset($output);

		$command = "git branch -r --contains $currentSha";
		exec($command, $stdout, $return);
		$output = $stdout;
		unset($stdout);

		if (count($output)) {
			foreach ($output as $o) {
				if (preg_match('/^origin\/release\/(.+)$/', trim($o), $matches)) {
					$branchName = $matches[0];
					$command = "git show --pretty=%H $branchName";
					exec($command, $stdout, $return);
					$output = $stdout;
					unset($stdout);

					$branchSha = $output[0];

					if ($branchSha == $currentSha) {
						$version = $matches[1];

						return $version;
					}
				}
			}
		}

		exec('git rev-list HEAD -500', $output, $return);

		foreach ($output as $sha) {
			exec('git tag --contains ' . $sha, $stdout, $return);
			$output = $stdout;
			unset($stdout);

			if ($output) {
				$version = array_shift($output);

				if ($version == $upperVersion) {
					continue;
				}

				exec("git show --pretty=%P $version", $stdout, $return);
				$tagParents = explode(" ", $stdout[0]);
				unset($stdout);

				if ($tagParents && !in_array($sha, $tagParents)) {
					continue;
				}

				return $version;
			}
		}

		return null;
	}
}
